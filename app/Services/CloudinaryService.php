<?php

namespace App\Services;

use App\Helpers\Env;
use Cloudinary\Cloudinary;
use RuntimeException;

class CloudinaryService
{
    private Cloudinary $cloudinary;

    public function __construct()
    {
        Env::load(dirname(__DIR__, 2) . '/.env');

        $cloudName = $_ENV['CLOUDINARY_CLOUD_NAME'] ?? null;
        $apiKey = $_ENV['CLOUDINARY_API_KEY'] ?? null;
        $apiSecret = $_ENV['CLOUDINARY_API_SECRET'] ?? null;

        if (!$cloudName || !$apiKey || !$apiSecret) {
            throw new RuntimeException(
                'Cloudinary configuration is incomplete.'
            );
        }

        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }

    /**
     * Upload an image to Cloudinary.
     *
     * Returns the secure HTTPS URL of the uploaded image.
     */
    public function uploadImage(
        string $filePath,
        string $folder = 'hairconnect/profile-images'
    ): string {
        if (!is_file($filePath)) {
            throw new RuntimeException(
                'Image file not found.'
            );
        }

        try {
            $result = $this->cloudinary
                ->uploadApi()
                ->upload(
                    $filePath,
                    [
                        'folder' => $folder,
                        'resource_type' => 'image',
                    ]
                );

            $secureUrl = $result['secure_url'] ?? null;

            if (!$secureUrl) {
                throw new RuntimeException(
                    'Cloudinary did not return an image URL.'
                );
            }

            return $secureUrl;

        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Image upload failed: ' . $e->getMessage()
            );
        }
    }
}