<?php

namespace Tests\Docs;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResponseRecorder
{
    public const DIRECTORY = __DIR__.'/examples';

    private const FAILURE_PRIORITY = [422, 403, 404, 401, 409, 429];

    private const TOKEN_KEYS = ['access_token', 'refresh_token', 'challenge_token', 'reset_token', 'password_token'];

    private static array $examples = [];

    private static bool $registered = false;

    public static function listen(Application $app): void
    {
        $app['events']->listen(RequestHandled::class, fn (RequestHandled $event) => self::record($event->request, $event->response));

        if (! self::$registered) {
            self::$registered = true;
            register_shutdown_function([self::class, 'write']);
        }
    }

    public static function record(Request $request, Response $response): void
    {
        $route = $request->route()?->getName();

        if ($route === null || ! Str::startsWith($route, 'v1.')) {
            return;
        }

        $status = $response->getStatusCode();
        $example = $response instanceof StreamedResponse
            ? ['status' => $status, 'content_type' => $response->headers->get('Content-Type'), 'body' => null]
            : ['status' => $status, 'content_type' => 'application/json', 'body' => self::clean(json_decode((string) $response->getContent(), true))];

        if ($example['content_type'] === 'application/json' && $example['body'] === null) {
            return;
        }

        $current = self::$examples[$route] ?? [];

        if ($status < 400) {
            if (! isset($current['success']) || self::weight($example) > self::weight($current['success'])) {
                $current['success'] = $example + ['request' => self::clean(self::input($request->all()))];
            }
        } elseif (! isset($current['failure']) || self::rank($status) < self::rank($current['failure']['status'])) {
            $current['failure'] = $example;
        }

        self::$examples[$route] = $current;
    }

    public static function write(): void
    {
        if (! is_dir(self::DIRECTORY)) {
            mkdir(self::DIRECTORY, 0755, true);
        }

        foreach (self::$examples as $route => $example) {
            file_put_contents(self::DIRECTORY.'/'.$route.'.json', json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        }
    }

    private static function clean(mixed $value, ?string $key = null): mixed
    {
        if (in_array($key, self::TOKEN_KEYS, true) && is_string($value)) {
            return '{'.$key.'}';
        }

        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            $value = array_slice($value, 0, $key === 'items' ? 2 : 3);
        }

        foreach ($value as $childKey => $child) {
            $value[$childKey] = self::clean($child, is_string($childKey) ? $childKey : $key);
        }

        return $value;
    }

    private static function input(array $input): array
    {
        return array_map(fn (mixed $value) => match (true) {
            $value instanceof UploadedFile => '(file)',
            is_array($value) => self::input($value),
            default => $value,
        }, $input);
    }

    private static function weight(array $example): int
    {
        return strlen((string) json_encode($example['body']));
    }

    private static function rank(int $status): int
    {
        $index = array_search($status, self::FAILURE_PRIORITY, true);

        return $index === false ? count(self::FAILURE_PRIORITY) + $status : $index;
    }
}
