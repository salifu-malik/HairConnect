<?php

namespace App\Services;

use Exception;

class PaystackService
{
    private string $secretKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = getenv('PAYSTACK_SECRET_KEY') ?: '';
        $this->baseUrl = getenv('PAYSTACK_BASE_URL')
            ?: 'https://api.paystack.co';

        if ($this->secretKey === '') {
            throw new Exception(
                'Paystack secret key is not configured.'
            );
        }
    }

    /**
     * Generate a unique HairConnect subscription payment reference.
     *
     * @return string
     */
    public function generateReference(): string
    {
        return 'HC_SUB_' .
            date('YmdHis') .
            '_' .
            strtoupper(
                bin2hex(random_bytes(4))
            );
    }


    /**
     * Initialize a Paystack transaction.
     *
     * @param string $email
     * @param float $amount Amount in GHS
     * @param string $reference Unique transaction reference
     * @param array $metadata Additional transaction metadata
     *
     * @return array
     *
     * @throws Exception
     */
    public function initializeTransaction(
        string $email,
        float $amount,
        string $reference,
        array $metadata = []
    ): array {
        $payload = [
            'email' => $email,

            // Paystack expects the amount in pesewas.
            'amount' => (int) round($amount * 100),

            'currency' => 'GHS',

            'reference' => $reference,

            'metadata' => $metadata
        ];

        $ch = curl_init(
            $this->baseUrl . '/transaction/initialize'
        );

        curl_setopt_array($ch, [
            CURLOPT_POST => true,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ],

            CURLOPT_POSTFIELDS => json_encode($payload),

            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);

            curl_close($ch);

            throw new Exception(
                'Unable to connect to Paystack: ' . $error
            );
        }

        $httpCode = curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close($ch);

        $decoded = json_decode(
            $response,
            true
        );

        if (!is_array($decoded)) {
            throw new Exception(
                'Invalid response received from Paystack.'
            );
        }

        if (
            $httpCode < 200 ||
            $httpCode >= 300 ||
            !($decoded['status'] ?? false)
        ) {
            $message =
                $decoded['message']
                ?? 'Paystack transaction initialization failed.';

            throw new Exception($message);
        }

        return $decoded['data'];
    }


    /**
     * Verify a Paystack transaction.
     *
     * @param string $reference
     *
     * @return array
     *
     * @throws Exception
     */
    public function verifyTransaction(
        string $reference
    ): array {
        $ch = curl_init(
            $this->baseUrl .
            '/transaction/verify/' .
            urlencode($reference)
        );

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->secretKey,
                'Accept: application/json'
            ],

            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);

            curl_close($ch);

            throw new Exception(
                'Unable to connect to Paystack: ' . $error
            );
        }

        $httpCode = curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close($ch);

        $decoded = json_decode(
            $response,
            true
        );

        if (!is_array($decoded)) {
            throw new Exception(
                'Invalid response received from Paystack.'
            );
        }

        if (
            $httpCode < 200 ||
            $httpCode >= 300 ||
            !($decoded['status'] ?? false)
        ) {
            $message =
                $decoded['message']
                ?? 'Paystack transaction verification failed.';

            throw new Exception($message);
        }

        return $decoded['data'];
    }
}