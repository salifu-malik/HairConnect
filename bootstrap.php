<?php

use App\Helpers\Env;

spl_autoload_register(function ($class) {
    $parts = explode('\\', $class);
    $parts[0] = strtolower($parts[0]);

    $file = __DIR__ . '/' . implode('/', $parts) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

Env::load(__DIR__ . '/.env');
require_once __DIR__ . '/vendor/autoload.php';