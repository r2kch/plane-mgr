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
    'invoice' => [
        'title' => 'Rechnung',
        'currency' => 'CHF',
        'payment_target_days' => 30,
        'vat' => [
            'enabled' => false,
            'rate_percent' => 8.1,
            'uid' => 'CHE-123.456.789 MWST',
        ],
        'issuer' => [
            'name' => 'Flugverein Demo',
            'street' => 'Flugplatzstrasse',
            'house_number' => '1',
            'postal_code' => '8000',
            'city' => 'Zuerich',
            'country' => 'Schweiz',
            'email' => 'verein@example.ch',
            'phone' => '+41 44 000 00 00',
            'website' => 'www.example.ch',
        ],
        'bank' => [
            'recipient' => 'Flugverein Demo',
            'iban' => 'CH93 0076 2011 6238 5295 7',
            'bic' => 'POFICHBEXXX',
            'bank_name' => 'PostFinance AG',
            'bank_address' => 'Mingerstrasse 20, 3030 Bern, Schweiz',
        ],
        'logo_path' => 'logo.png',
    ],
    'modules' => [
        'reservations' => true,
        'billing' => true,
        'audit' => true,
    ],
];
