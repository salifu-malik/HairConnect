<?php

namespace App\Helpers;

use Redis;
use RuntimeException;

class RedisManager
{
    private Redis $redis;

    public function __construct()
    {
        $this->redis = new Redis();

        try {
            $this->redis->connect(
                $_ENV['REDIS_HOST'] ?? '127.0.0.1',
                (int) ($_ENV['REDIS_PORT'] ?? 6379),
                2.0
            );

            if (!empty($_ENV['REDIS_PASSWORD'])) {
                $this->redis->auth($_ENV['REDIS_PASSWORD']);
            }

            $this->redis->ping();
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Redis connection failed: ' . $e->getMessage()
            );
        }
    }

    public function get(string $key): mixed
    {
        $value = $this->redis->get($key);

        if ($value === false) {
            return null;
        }

        return json_decode($value, true);
    }

    public function set(
        string $key,
        mixed $value,
        int $ttl = 300
    ): bool {
        return $this->redis->set(
            $key,
            json_encode($value),
            $ttl
        );
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($key) > 0;
    }

    public function exists(string $key): bool
    {
        return $this->redis->exists($key) > 0;
    }

    public function clear(): void
    {
        $this->redis->flushDB();
    }
}