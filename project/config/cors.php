<?php

return [
    'paths' => ['api/*', 'api', 'scalar', 'scalar/*', 'sanctum/csrf-cookie'],
    'allowed_methods'   => ['*'],
    'allowed_origins'   => [
        'https://68.211.72.129',
        'http://localhost:8000',
    ],
    'allowed_origins_patterns' => [
        '/^https?:\/\/([a-z0-9-]+\.)?68.211.72.129$/',
    ],
    'allowed_headers'   => ['*'],
    'exposed_headers'   => [],
    'max_age'           => 3600,
    'supports_credentials' => false,
];

