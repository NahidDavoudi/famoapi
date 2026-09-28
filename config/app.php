<?php

return [
    'jwt' => [
        'secret'    => $_ENV['JWT_SECRET'] ?? 'famo-jwt-secret-change-in-production',
        'ttl'       => (int) ($_ENV['JWT_TTL'] ?? 86400),
        'algorithm' => 'HS256',
    ],
    'cors' => [
        'origins' => explode(',', $_ENV['CORS_ORIGINS'] ?? '*'),
    ],
    'upload' => [
        'path'     => $_ENV['UPLOADS_PATH'] ?? __DIR__ . '/../uploads',
        'max_size' => (int) ($_ENV['UPLOAD_MAX_SIZE'] ?? 10 * 1024 * 1024),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'],
    ],
    'app' => [
        'env'   => $_ENV['APP_ENV'] ?? 'production',
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ],
];