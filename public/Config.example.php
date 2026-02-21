<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Plane Manager',
        'url' => 'https://example.com',
        'env' => 'prod',
        'timezone' => 'Europe/Zurich',
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'plane_mgr',
        'user' => 'db_user',
        'pass' => 'db_password',
    ],
    'smtp' => [
        'enabled' => true,
        'host' => 'smtp.example.com',
        'port' => 587,
        'user' => 'smtp_user',
        'pass' => 'smtp_password',
        'from' => 'no-reply@example.com',
        'from_name' => 'Plane Manager',
    ],
    'invoice' => [
        'title' => 'Rechnung',
        'currency' => 'CHF',
        'payment_target_days' => 30,
        'vat' => [
            'enabled' => true,
            'rate_percent' => 8.1,
            'uid' => 'CHE-123.456.789 MWST',
        ],
        'issuer' => [
            'name' => 'Flugverein Demo',
            'street' => 'Flugplatzstrasse',
            'house_number' => '1',
            'postal_code' => '8000',
            'city' => 'Zürich',
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
    'news' => [
        'allowed_tags' => ['b', 'strong', 'i', 'em', 'u', 'span', 'p', 'br', 'ul', 'ol', 'li', 'div'],
    ],
];
