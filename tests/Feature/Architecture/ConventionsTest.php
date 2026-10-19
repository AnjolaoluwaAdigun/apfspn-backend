<?php

namespace Tests\Feature\Architecture;

use App\Enums\Concerns\EnumHelpers;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;

class ConventionsTest extends TestCase
{
    private const PUBLIC_ADMIN_AUTH = ['login', 'verify-otp', 'resend-otp', 'forgot-password', 'reset-password'];

    private const TOKEN_ONLY_ADMIN = ['v1.admin.auth.refresh', 'v1.admin.auth.logout', 'v1.admin.auth.me'];

    private const SLUG_PARAMETERS = ['slug'];

    public function test_every_admin_route_is_authenticated_and_permission_checked(): void
    {
        $problems = [];

        foreach ($this->apiRoutes()->filter(fn (Route $route) => Str::startsWith($route->getName(), 'v1.admin.')) as $route) {
            if (Str::startsWith($route->getName(), 'v1.admin.auth.') && in_array(Str::after($route->uri(), 'auth/'), self::PUBLIC_ADMIN_AUTH, true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $hasPermission = collect($middleware)->contains(fn ($name) => is_string($name) && Str::startsWith($name, 'permission:'));

            if (! in_array('auth:sanctum', $middleware, true) || ! collect($middleware)->contains(fn ($name) => is_string($name) && Str::startsWith($name, 'token:admin'))) {
                $problems[] = $route->getName().' is missing auth:sanctum or token:admin';
            }

            if (! $hasPermission && ! in_array($route->getName(), self::TOKEN_ONLY_ADMIN, true)) {
                $problems[] = $route->getName().' has no permission middleware';
            }
        }

        $this->assertSame([], $problems);
    }

    public function test_every_member_route_behind_a_token_checks_the_member_audience(): void
    {
        $problems = $this->apiRoutes()
            ->filter(fn (Route $route) => Str::startsWith($route->getName(), 'v1.member.') && in_array('auth:sanctum', $route->gatherMiddleware(), true))
            ->reject(fn (Route $route) => collect($route->gatherMiddleware())->contains(fn ($name) => is_string($name) && Str::startsWith($name, 'token:member')))
            ->map(fn (Route $route) => $route->getName())
            ->values()
            ->all();

        $this->assertSame([], $problems);
    }

    public function test_every_route_parameter_is_a_uuid_or_a_slug(): void
    {
        $problems = [];

        foreach ($this->apiRoutes() as $route) {
            foreach ($route->parameterNames() as $parameter) {
                if (in_array($parameter, self::SLUG_PARAMETERS, true)) {
                    continue;
                }

                if (($route->wheres[$parameter] ?? null) !== '[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}') {
                    $problems[] = $route->getName().' {'.$parameter.'}';
                }
            }
        }

        $this->assertSame([], $problems);
    }

    public function test_every_endpoint_has_its_own_form_request(): void
    {
        $problems = [];
        $seen = [];

        foreach ($this->apiRoutes() as $route) {
            $request = $this->formRequestOf($route);

            if ($request === null) {
                $problems[] = $route->getName().' has no ApiFormRequest';

                continue;
            }

            if (isset($seen[$request])) {
                $problems[] = $route->getName().' shares '.class_basename($request).' with '.$seen[$request];
            }

            $seen[$request] = $route->getName();
        }

        $this->assertSame([], $problems);
    }

    public function test_every_enum_uses_the_shared_helpers(): void
    {
        $problems = collect(File::files(app_path('Enums')))
            ->map(fn ($file) => 'App\\Enums\\'.$file->getFilenameWithoutExtension())
            ->reject(fn (string $enum) => in_array(EnumHelpers::class, class_uses($enum), true))
            ->values()
            ->all();

        $this->assertSame([], $problems);
    }

    public function test_services_do_not_query_models_directly(): void
    {
        $models = collect(File::files(app_path('Models')))->map(fn ($file) => $file->getFilenameWithoutExtension())->implode('|');
        $pattern = '/\b(?:'.$models.')::(?!class\b)[a-z]\w*\(|DB::(?:table|select|statement|update|insert|delete)\(/';
        $problems = [];

        foreach (File::allFiles(app_path('Services')) as $file) {
            foreach (preg_split('/\R/', $file->getContents()) as $number => $line) {
                if (preg_match($pattern, $line) || preg_match('/\$(?!this\b)\w+->(?:(?:membership|permissions|roles|tokens|registrations|speakers|revisions)\(\)->|(?:syncRoles|syncPermissions|assignRole|givePermissionTo|getAllPermissions|getRoleNames)\(|roles->)/', $line)) {
                    $problems[] = Str::after($file->getPathname(), base_path().DIRECTORY_SEPARATOR).':'.($number + 1);
                }
            }
        }

        $this->assertSame([], $problems);
    }

    public function test_resources_never_expose_integer_ids(): void
    {
        $problems = collect(File::files(app_path('Http/Resources')))
            ->filter(fn ($file) => preg_match("/'id'\s*=>|->id\b/", $file->getContents()))
            ->map(fn ($file) => $file->getFilename())
            ->values()
            ->all();

        $this->assertSame([], $problems);
    }

    public function test_controllers_respond_through_the_envelope(): void
    {
        $problems = [];

        foreach (File::allFiles(app_path('Http/Controllers')) as $file) {
            foreach (preg_split('/\R/', $file->getContents()) as $number => $line) {
                if (preg_match('/^\s*return\s+(?!JsonResponser::|\$this->respond\(|response\(\)->streamDownload\()/', $line)) {
                    $problems[] = $file->getFilename().':'.($number + 1);
                }
            }
        }

        $this->assertSame([], $problems);
    }

    private function apiRoutes(): Collection
    {
        return collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => Str::startsWith($route->uri(), 'api/v1'))
            ->values();
    }

    private function formRequestOf(Route $route): ?string
    {
        $action = $route->getActionName();

        if ($action === 'Closure') {
            return null;
        }

        [$controller, $method] = Str::contains($action, '@') ? explode('@', $action) : [$action, '__invoke'];

        foreach ((new ReflectionMethod($controller, $method))->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_subclass_of($type->getName(), ApiFormRequest::class)) {
                return (new ReflectionClass($type->getName()))->getName();
            }
        }

        return null;
    }
}
