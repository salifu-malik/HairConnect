<?php

namespace App\Helpers;

class RefreshTokenCookieManager implements RefreshTokenCookieManagerInterface
{
    public function set(string $refreshToken): void
    {
        $secure = getenv('COOKIE_SECURE') === 'true';

        setcookie('refresh_token', $refreshToken, [
            'expires' => time() + (7 * 24 * 60 * 60),
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => getenv('COOKIE_SAMESITE') ?: 'Lax',
        ]);
    }

    public function clear(): void
    {
        $secure = getenv('COOKIE_SECURE') === 'true';

        setcookie('refresh_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => getenv('COOKIE_SAMESITE') ?: 'Lax',
        ]);
    }
}