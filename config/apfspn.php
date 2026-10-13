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

    'uploads' => [
        'disk' => 'cloudinary',
        'cloudinary_host' => 'res.cloudinary.com',
        'cloud_name' => env('CLOUDINARY_URL') ? parse_url(env('CLOUDINARY_URL'), PHP_URL_HOST) : env('CLOUDINARY_CLOUD_NAME'),
        'max_base64_bytes' => 10 * 1024 * 1024,
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
