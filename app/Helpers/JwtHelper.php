<?php

namespace App\Helpers;

use RuntimeException;

class JwtHelper
{
    private static ?string $privateKey = null;
    private static ?string $publicKey = null;

    private static function getPrivateKey(): string
    {
        if (self::$privateKey === null) {

            $path = __DIR__ . '/../../keys/private.pem';
            error_log("JWT PRIVATE KEY PATH: " . $path);
            error_log("JWT PRIVATE KEY EXISTS: " . (is_file($path) ? 'YES' : 'NO'));
            error_log("JWT PRIVATE KEY READABLE: " . (is_readable($path) ? 'YES' : 'NO'));

            if (!is_file($path)) {
                throw new RuntimeException(
                    "JWT private key file not found."
                );
            }

            if (!is_readable($path)) {
                throw new RuntimeException(
                    "JWT private key file is not readable."
                );
            }

            $key = file_get_contents($path);

            if ($key === false || trim($key) === '') {
                throw new RuntimeException(
                    "JWT private key is unavailable."
                );
            }

            self::$privateKey = $key;
        }

        return self::$privateKey;
    }

    private static function getPublicKey(): string
    {
        if (self::$publicKey === null) {

            $path = __DIR__ . '/../../keys/public.pem';
            error_log("JWT PRIVATE KEY PATH: " . $path);
            error_log("JWT PRIVATE KEY EXISTS: " . (is_file($path) ? 'YES' : 'NO'));
            error_log("JWT PRIVATE KEY READABLE: " . (is_readable($path) ? 'YES' : 'NO'));

            if (!is_file($path)) {
                throw new RuntimeException(
                    "JWT public key file not found."
                );
            }

            if (!is_readable($path)) {
                throw new RuntimeException(
                    "JWT public key file is not readable."
                );
            }

            $key = file_get_contents($path);

            if ($key === false || trim($key) === '') {
                throw new RuntimeException(
                    "JWT public key is unavailable."
                );
            }

            self::$publicKey = $key;
        }

        return self::$publicKey;
    }

    public static function encode(
        array $payload,
        int   $expiry = 3600
    ): string
    {

        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];

        $payload['iat'] = time();
        $payload['exp'] = time() + $expiry;

        $base64UrlHeader = self::base64UrlEncode(
            json_encode($header, JSON_THROW_ON_ERROR)
        );

        $base64UrlPayload = self::base64UrlEncode(
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        $data = $base64UrlHeader . '.' . $base64UrlPayload;

        $signature = '';

        $result = openssl_sign(
            $data,
            $signature,
            self::getPrivateKey(),
            OPENSSL_ALGO_SHA256
        );

        if ($result !== true) {
            throw new RuntimeException(
                'Unable to sign JWT.'
            );
        }

        $base64UrlSignature = self::base64UrlEncode(
            $signature
        );

        return $data . '.' . $base64UrlSignature;
    }

    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [
            $base64UrlHeader,
            $base64UrlPayload,
            $base64UrlSignature
        ] = $parts;

        $headerJson = self::base64UrlDecode($base64UrlHeader);

        $header = json_decode($headerJson, true);

        if (!is_array($header)) {
            return null;
        }

        if (($header['alg'] ?? null) !== 'RS256') {
            return null;
        }

        $data = $base64UrlHeader . '.' . $base64UrlPayload;

        $signature = self::base64UrlDecode(
            $base64UrlSignature
        );

        $result = openssl_verify(
            $data,
            $signature,
            self::getPublicKey(),
            OPENSSL_ALGO_SHA256
        );

        if ($result !== 1) {
            return null;
        }

        $payload = json_decode(
            self::base64UrlDecode($base64UrlPayload),
            true
        );

        if (!is_array($payload)) {
            return null;
        }

        if (
            !isset($payload['exp']) ||
            !is_numeric($payload['exp']) ||
            $payload['exp'] < time()
        ) {
            return null;
        }

        return $payload;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(
            strtr(
                base64_encode($data),
                '+/',
                '-_'
            ),
            '='
        );
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;

        if ($remainder) {
            $data .= str_repeat(
                '=',
                4 - $remainder
            );
        }

        return base64_decode(
            strtr($data, '-_', '+/')
        );
    }
}