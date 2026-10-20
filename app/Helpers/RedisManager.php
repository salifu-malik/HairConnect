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



    public function increment(string $key, int $ttl = 900): int
    {
        if ($ttl < 1) {
            throw new \InvalidArgumentException(
                'TTL must be greater than zero.'
            );
        }

        $script = <<<'LUA'
local count = redis.call('INCR', KEYS[1])

if count == 1 then
    redis.call('EXPIRE', KEYS[1], ARGV[1])
end

return count
LUA;

        $result = $this->redis->eval(
            $script,
            [$key, $ttl],
            1
        );

        if ($result === false || $result === null) {
            throw new \RuntimeException(
                'Unable to increment Redis counter.'
            );
        }

        return (int) $result;
    }


    public function clear(): void
    {
        $this->redis->flushDB();
    }
}