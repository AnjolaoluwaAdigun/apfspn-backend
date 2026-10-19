<?php

return [

    'api_version' => 'v1',

    'member_id_prefix' => 'APFSPN',

    'states' => [
        'AB' => 'Abia',
        'AD' => 'Adamawa',
        'AK' => 'Akwa Ibom',
        'AN' => 'Anambra',
        'BA' => 'Bauchi',
        'BY' => 'Bayelsa',
        'BE' => 'Benue',
        'BO' => 'Borno',
        'CR' => 'Cross River',
        'DE' => 'Delta',
        'EB' => 'Ebonyi',
        'ED' => 'Edo',
        'EK' => 'Ekiti',
        'EN' => 'Enugu',
        'FC' => 'Federal Capital Territory',
        'GO' => 'Gombe',
        'IM' => 'Imo',
        'JI' => 'Jigawa',
        'KD' => 'Kaduna',
        'KN' => 'Kano',
        'KT' => 'Katsina',
        'KE' => 'Kebbi',
        'KO' => 'Kogi',
        'KW' => 'Kwara',
        'LA' => 'Lagos',
        'NA' => 'Nasarawa',
        'NI' => 'Niger',
        'OG' => 'Ogun',
        'ON' => 'Ondo',
        'OS' => 'Osun',
        'OY' => 'Oyo',
        'PL' => 'Plateau',
        'RI' => 'Rivers',
        'SO' => 'Sokoto',
        'TA' => 'Taraba',
        'YO' => 'Yobe',
        'ZA' => 'Zamfara',
    ],

    'auth' => [
        'access_ttl_minutes' => (int) env('APFSPN_ACCESS_TTL_MINUTES', 60),
        'refresh_ttl_days' => (int) env('APFSPN_REFRESH_TTL_DAYS', 30),
        'otp_length' => 6,
        'otp_ttl_minutes' => 10,
        'resend_cooldown_seconds' => 60,
        'max_sends' => 5,
        'max_attempts' => 5,
        'reset_token_ttl_minutes' => 15,
        'password_setup_ttl_minutes' => 60,
        'invite_token_ttl_hours' => 72,
        'admin_two_factor' => (bool) env('APFSPN_ADMIN_TWO_FACTOR', false),
        'super_admin' => [
            'name' => env('SUPER_ADMIN_NAME'),
            'email' => env('SUPER_ADMIN_EMAIL'),
        ],
    ],

    'reporting_timezone' => 'Africa/Lagos',

    'dashboard' => [
        'cache_ttl_seconds' => 60,
    ],

    'frontend' => [
        'member_url' => env('FRONTEND_MEMBER_URL', 'http://localhost:3000'),
        'admin_url' => env('FRONTEND_ADMIN_URL', 'http://localhost:3001'),
    ],

    'uploads' => [
        'disk' => 'cloudinary',
        'cloudinary_host' => 'res.cloudinary.com',
        'cloud_name' => env('CLOUDINARY_URL') ? parse_url(env('CLOUDINARY_URL'), PHP_URL_HOST) : env('CLOUDINARY_CLOUD_NAME'),
        'max_base64_bytes' => 10 * 1024 * 1024,
        'private_url_ttl_minutes' => 10,
        'folders' => [
            'member_photos' => 'members/photos',
            'registration_documents' => 'members/documents',
            'event_images' => 'events/images',
            'event_speakers' => 'events/speakers',
            'article_covers' => 'articles/covers',
        ],
    ],

    'encryption' => [
        'enabled' => (bool) env('APFSPN_ENCRYPTION_ENABLED', false),
        'key' => env('APFSPN_ENCRYPTION_KEY'),
        'cipher' => 'aes-256-cbc',
        'except' => [
            'api/v1/webhooks/*',
        ],
    ],

];
