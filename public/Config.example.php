<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Plane Manager',
        'url' => 'https://example.com',
        'env' => 'prod',
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'plane_mgr',
        'user' => 'db_user',
        'pass' => 'db_password',
    ],
    'smtp' => [
        'host' => 'smtp.example.com',
        'port' => 587,
        'user' => 'smtp_user',
        'pass' => 'smtp_password',
        'from' => 'no-reply@example.com',
        'from_name' => 'Plane Manager',
    ],
];
