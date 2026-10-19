<?php

use Knuckles\Scribe\Config\AuthIn;
use Knuckles\Scribe\Config\Defaults;
use Knuckles\Scribe\Extracting\Strategies;
use Tests\Docs\Strategies\BodyParametersFromFormRequest;
use Tests\Docs\Strategies\CapturedResponses;
use Tests\Docs\Strategies\QueryParametersFromFormRequest;
use Tests\Docs\Strategies\RouteMetadata;

if (! class_exists(Defaults::class) || ! class_exists(RouteMetadata::class)) {
    return [];
}

return [
    'title' => 'APFSPN API',

    'description' => 'REST API for the APFSPN public website, member portal and admin dashboard.',

    'intro_text' => <<<'INTRO'
            All endpoints live under `/api/v1` and are split by audience: public, member (`/member`) and admin (`/admin`).

            **Envelope.** Every JSON response has exactly three keys: `{"error": bool, "message": string, "data": mixed}`. `error: false` means success. Lists return `data = {"items": [...], "pagination": {"current_page", "per_page", "total", "last_page"}}`. Failures carry a machine-readable `data.code` (for example `invalid_credentials`, `blocked`, `invalid_status_transition`); validation failures (422) return the first message plus every field error in `data`.

            **Auth.** Sign in with the audience's `auth/login` endpoint. Members with two-factor enabled (the default) get `next_step: verify_otp` and finish with `verify-login-otp`; admins do the same only when admin two-factor is switched on. A successful login returns an access token and a refresh token. Send the access token as `Authorization: Bearer <token>`; send the refresh token the same way to `auth/refresh` to rotate both. Member tokens are refused on admin routes and the other way round (`wrong_audience`).

            **Permissions.** Admin endpoints list the permission they need. A missing permission returns 403.

            **IDs.** Every identifier in a URL or response is a UUID.

            **Examples.** The example responses are real responses recorded from the feature test suite.
        INTRO,

    'base_url' => 'http://localhost:8000',

    'routes' => [
        [
            'match' => [
                'prefixes' => ['api/v1/*'],
                'domains' => ['*'],
            ],
            'include' => [],
            'exclude' => [],
        ],
    ],

    'type' => 'static',

    'theme' => 'default',

    'static' => [
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        'add_routes' => false,
        'docs_url' => '/docs',
        'assets_directory' => null,
        'middleware' => [],
    ],

    'external' => [
        'html_attributes' => [],
    ],

    'try_it_out' => [
        'enabled' => true,
        'base_url' => null,
        'use_csrf' => false,
        'csrf_url' => '/sanctum/csrf-cookie',
    ],

    'auth' => [
        'enabled' => true,
        'default' => false,
        'in' => AuthIn::BEARER->value,
        'name' => 'Authorization',
        'use_value' => null,
        'placeholder' => '{ACCESS_TOKEN}',
        'extra_info' => 'Get a token from `POST /api/v1/admin/auth/login` or `POST /api/v1/member/auth/login`. With the demo data seeded, admins sign in with `superadmin@apfspn.test` / `Password123!` and members with `adaobi@apfspn.test` / `Member123!`.',
    ],

    'example_languages' => [
        'bash',
        'javascript',
    ],

    'postman' => [
        'enabled' => true,
        'overrides' => [],
    ],

    'openapi' => [
        'enabled' => true,
        'version' => '3.0.3',
        'overrides' => [],
        'generators' => [],
    ],

    'groups' => [
        'default' => 'Public',
        'order' => [
            'Public',
            'Admin Auth',
            'Admin Dashboard',
            'Admin Members',
            'Admin Events',
            'Admin Articles',
            'Admin Users',
            'Admin Roles',
            'Member Auth',
            'Member Dashboard',
            'Member Registration',
            'Member Events',
            'Public Articles',
        ],
    ],

    'logo' => false,

    'last_updated' => 'Last updated: {date:F j, Y}',

    'examples' => [
        'faker_seed' => 1234,
        'models_source' => ['factoryMake'],
    ],

    'strategies' => [
        'metadata' => [
            RouteMetadata::class,
        ],
        'headers' => [
            Strategies\StaticData::withSettings(data: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
        ],
        'urlParameters' => [
            ...Defaults::URL_PARAMETERS_STRATEGIES,
        ],
        'queryParameters' => [
            QueryParametersFromFormRequest::class,
        ],
        'bodyParameters' => [
            BodyParametersFromFormRequest::class,
        ],
        'responses' => [
            CapturedResponses::class,
        ],
        'responseFields' => [],
    ],

    'database_connections_to_transact' => [],

    'fractal' => [
        'serializer' => null,
    ],
];
