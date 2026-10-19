<?php

namespace Tests\Docs\Strategies;

use Illuminate\Support\Str;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\Strategy;

class RouteMetadata extends Strategy
{
    private const COLLECTION = __DIR__.'/../../../postman/apfspn.postman_collection.json';

    private static ?array $index = null;

    public function __invoke(ExtractedEndpointData $endpointData, array $settings = []): ?array
    {
        $method = $endpointData->httpMethods[0];
        $entry = self::index()[$method.' '.self::normalise(Str::after($endpointData->uri, 'api/v1/'))] ?? null;
        $middleware = $endpointData->route->gatherMiddleware();

        return [
            'groupName' => $entry['group'] ?? 'Public',
            'groupDescription' => $entry['group_description'] ?? '',
            'title' => $entry['title'] ?? Str::headline($endpointData->route->getActionMethod()),
            'description' => trim(self::access($middleware)."\n\n".($entry['description'] ?? '')),
            'authenticated' => in_array('auth:sanctum', $middleware, true),
        ];
    }

    private static function access(array $middleware): string
    {
        $lines = [];

        foreach ($middleware as $name) {
            if (! is_string($name)) {
                continue;
            }

            [$alias, $arguments] = array_pad(explode(':', $name, 2), 2, '');

            $lines[] = match ($alias) {
                'token' => match ($arguments) {
                    'admin' => '**Auth:** admin access token.',
                    'member' => '**Auth:** member access token.',
                    'admin,refresh' => '**Auth:** admin refresh token sent as the bearer token.',
                    'member,refresh' => '**Auth:** member refresh token sent as the bearer token.',
                    default => null,
                },
                'permission' => '**Permission:** `'.$arguments.'`.',
                'can' => '**Policy:** `'.Str::before($arguments, ',').'` is also checked on the record.',
                'throttle' => '**Rate limit:** `'.$arguments.'`.',
                default => null,
            };
        }

        return implode("\n\n", array_filter($lines));
    }

    private static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }

        self::$index = [];
        $collection = json_decode((string) file_get_contents(self::COLLECTION), true);

        foreach ($collection['item'] as $folder) {
            foreach ($folder['item'] ?? [] as $item) {
                $request = $item['request'];
                $raw = is_array($request['url']) ? $request['url']['raw'] : $request['url'];
                $key = $request['method'].' '.self::normalise(Str::before(Str::after($raw, '{{base_url}}/'), '?'));

                self::$index[$key] ??= [
                    'group' => $folder['name'],
                    'group_description' => $folder['description'] ?? '',
                    'title' => $item['name'],
                    'description' => preg_replace('/^Permission:.*?\.(?=\s|$)\s*/', '', $request['description'] ?? ''),
                ];
            }
        }

        return self::$index;
    }

    private static function normalise(string $path): string
    {
        return preg_replace(['/\{\{[^}]+\}\}/', '/\{[^}]+\}/'], '*', trim($path, '/'));
    }
}
