<?php

namespace App\Helpers;

use PDO;
use Exception;

class DatabaseManager {
    private static array $instances = [];
    private static array $config;

    public static function init(array $config) {
        self::$config = $config;
    }

    public static function getConnection(string $name): PDO {
        if (!isset(self::$instances[$name])) {
            if (!isset(self::$config['connections'][$name])) {
                throw new Exception("Database connection '$name' not configured.");
            }

            $db = self::$config['connections'][$name];
            $dsn = "mysql:host={$db['host']};dbname={$db['dbname']};charset=utf8mb4";
            
            try {
                self::$instances[$name] = new PDO(
                    $dsn,
                    $db['user'],
                    $db['password'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );

// Keep database timestamps in UTC.
// Ghana (Africa/Accra) is UTC+0.
                self::$instances[$name]->exec(
                    "SET time_zone = '+00:00'"
                );
            } catch (Exception $e) {
                throw new Exception("Could not connect to database '$name': " . $e->getMessage());
            }
        }

        return self::$instances[$name];
    }
}
