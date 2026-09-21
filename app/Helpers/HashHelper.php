<?php

namespace App\Helpers;

class HashHelper
{
    /**
     * Encodes an integer ID into a base64 string to obfuscate it.
     */
    public static function encode(int $id): string
    {
        return base64_encode((string)$id);
    }

    /**
     * Decodes a base64 encoded string back into an integer ID.
     */
    public static function decode(string $hash): int
    {
        return (int)base64_decode($hash);
    }
}
