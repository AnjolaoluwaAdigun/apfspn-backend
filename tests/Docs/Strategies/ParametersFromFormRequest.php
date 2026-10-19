<?php

namespace Tests\Docs\Strategies;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\GetFromFormRequestBase;
use ReflectionNamedType;
use Tests\Docs\ResponseRecorder;
use Throwable;

abstract class ParametersFromFormRequest extends GetFromFormRequestBase
{
    abstract protected function appliesTo(string $httpMethod): bool;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        if (! $this->appliesTo($endpointData->httpMethods[0])) {
            return [];
        }

        return $this->getParametersFromFormRequest($endpointData->method, $endpointData->route);
    }

    public function getParametersFromFormRequest(\ReflectionFunctionAbstract $method, Route $route): array
    {
        if (! $reflection = $this->getFormRequestReflectionClass($method)) {
            return [];
        }

        $className = $reflection->getName();
        /** @var FormRequest $formRequest */
        $formRequest = new $className;
        $bound = (clone $route)->bind($formRequest);

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), Model::class)) {
                $bound->setParameter($parameter->getName(), (new ($type->getName()))->forceFill(['uuid' => '00000000-0000-0000-0000-000000000000']));
            }
        }

        $user = str_starts_with((string) $route->getName(), 'v1.admin.') ? new Admin : new User;

        $formRequest->setRouteResolver(fn () => $bound);
        $formRequest->setUserResolver(fn () => $user);
        $formRequest->server->set('REQUEST_METHOD', $route->methods()[0]);

        $rules = $this->getRouteValidationRules($formRequest);

        return $this->normaliseArrayAndObjectParameters(
            $this->getParametersFromValidationRules($rules, $this->examples(array_keys($rules), (string) $route->getName()))
        );
    }

    private function examples(array $parameters, string $routeName): array
    {
        $file = ResponseRecorder::DIRECTORY.'/'.$routeName.'.json';
        $request = is_file($file) ? (json_decode((string) file_get_contents($file), true)['success']['request'] ?? []) : [];
        $examples = [];

        foreach ($parameters as $parameter) {
            $path = str_replace('*', '0', $parameter);
            $examples[$parameter] = ['example' => data_get($request, $path) ?? 'No-example'];
        }

        return $examples;
    }

    protected function getRouteValidationRules(FormRequest $formRequest)
    {
        try {
            $rules = parent::getRouteValidationRules($formRequest);
        } catch (Throwable) {
            return [];
        }

        return array_filter(
            $rules,
            fn (mixed $rules) => ! in_array('prohibited', is_string($rules) ? explode('|', $rules) : (array) $rules, true),
        );
    }
}
