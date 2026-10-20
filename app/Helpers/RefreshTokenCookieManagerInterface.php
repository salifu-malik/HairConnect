<?php

namespace App\Helpers;

interface RefreshTokenCookieManagerInterface
{
    public function set(string $refreshToken): void;

    public function clear(): void;
}