<?php

namespace App\Helpers;

use RuntimeException;

class TurnstileVerifier
{
    private const VERIFY_URL =
        'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function verify(string $token): bool
    {
        $token = trim($token);

        if ($token === '') {
            return false;
        }

        $secret = getenv('TURNSTILE_SECRET_KEY');

        if (!$secret) {
            error_log('Turnstile verification failed: secret key is not configured.');
            throw new RuntimeException(
                'Security verification is temporarily unavailable.'
            );
        }

        if (!function_exists('curl_init')) {
            error_log('Turnstile verification failed: PHP cURL extension is unavailable.');
            throw new RuntimeException(
                'Security verification is temporarily unavailable.'
            );
        }

        $payload = http_build_query([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        $ch = curl_init(self::VERIFY_URL);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($response === false || $httpStatus !== 200) {
            error_log(
                'Turnstile verification request failed. HTTP status: ' .
                $httpStatus . '; cURL error: ' . $curlError
            );

            throw new RuntimeException(
                'Security verification is temporarily unavailable.'
            );
        }

        $result = json_decode($response, true);

        if (!is_array($result)) {
            error_log('Turnstile verification returned an invalid response.');
            throw new RuntimeException(
                'Security verification is temporarily unavailable.'
            );
        }

        return ($result['success'] ?? false) === true;
    }
}