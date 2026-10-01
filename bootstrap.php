<?php

date_default_timezone_set('Africa/Accra');

use App\Helpers\Env;
use App\Helpers\DatabaseManager;

spl_autoload_register(function ($class) {
    $parts = explode('\\', $class);
    $parts[0] = strtolower($parts[0]);

    $file = __DIR__ . '/' . implode('/', $parts) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

require_once __DIR__ . '/vendor/autoload.php';

Env::load(__DIR__ . '/.env');

$config = require __DIR__ . '/config/database.php';

DatabaseManager::init($config);