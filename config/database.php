<?php

return [
    'default' => 'users_db',
    'connections' => [
        'users_db' => [
            'host' => $_ENV['DB_HOST'],
            'dbname' => 'users_db',
            'user' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASSWORD'],
        ],
        'booking_db' => [
            'host' => $_ENV['DB_HOST'],
            'dbname' => 'booking_db',
            'user' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASSWORD'],
        ],
        'store_db' => [
            'host' => $_ENV['DB_HOST'],
            'dbname' => 'store_db',
            'user' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASSWORD'],
        ],
        'payment_db' => [
            'host' => $_ENV['DB_HOST'],
            'dbname' => 'payment_db',
            'user' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASSWORD'],
        ],
        'wallet_db' => [
            'host' => $_ENV['DB_HOST'],
            'dbname' => 'wallet_db',
            'user' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASSWORD'],
        ],
        'subscription_db' => [
            'host' => $_ENV['DB_HOST'],
            'dbname' => 'subscription_db',
            'user' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASSWORD'],
        ],
        'delivery_db' => [
            'host' => $_ENV['DB_HOST'],
            'dbname' => 'delivery_db',
            'user' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASSWORD'],
        ],
        'notification_db' => [
            'host' => $_ENV['DB_HOST'],
            'dbname' => 'notification_db',
            'user' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASSWORD'],
        ],
        'analytics_db' => [
            'host' => $_ENV['DB_HOST'],
            'dbname' => 'analytics_db',
            'user' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASSWORD'],
        ],
        'pricing_db' => [
            'host' => $_ENV['DB_HOST'],
            'dbname' => 'pricing_db',
            'user' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASSWORD'],
        ],
        // ... add other databases
    ]
];
