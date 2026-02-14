<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Plane Manager',
        'url' => 'http://localhost:8888',
        'env' => 'local',
    ],
    'db' => [
        'host' => 'db',
        'port' => 3306,
        'name' => 'plane_mgr',
        'user' => 'plane',
        'pass' => 'planepass',
    ],
    'smtp' => [
        'host' => '',
        'port' => 587,
        'user' => '',
        'pass' => '',
        'from' => 'no-reply@example.com',
        'from_name' => 'Plane Manager',
    ],
    'modules' => [
        'reservations' => true,
        'billing' => true,
    ],
];
