<?php

namespace App\Services;

use App\Models\Service;
use App\Repositories\ServiceRepository;
use App\Repositories\ShopRepository;
use Exception;

class ShopOwnerServiceService
{
    private ServiceRepository $serviceRepository;
    private ShopRepository $shopRepository;

    public function __construct(
        ServiceRepository $serviceRepository,
        ShopRepository $shopRepository
    ) {
        $this->serviceRepository = $serviceRepository;
        $this->shopRepository = $shopRepository;
    }

    /**
     * Verify that a shop exists and belongs to the authenticated owner.
     */
    private function getOwnedShop(int $ownerId, int $shopId)
    {
        if ($ownerId <= 0) {
            throw new Exception('Invalid shop owner.');
        }

        if ($shopId <= 0) {
            throw new Exception('Invalid shop.');
        }

        $shop = $this->shopRepository->findById($shopId);

        if (!$shop) {
            throw new Exception('Shop not found.');
        }

        if ($shop->ownerId !== $ownerId) {
            throw new Exception(
                'You are not authorized to manage services for this shop.'
            );
        }

        return $shop;
    }

    /**
     * Verify that a service exists and belongs to a shop
     * owned by the authenticated user.
     */
    private function getOwnedService(
        int $ownerId,
        int $serviceId
    ): Service {
        if ($serviceId <= 0) {
            throw new Exception('Invalid service.');
        }

        $service = $this->serviceRepository->findById($serviceId);

        if (!$service) {
            throw new Exception('Service not found.');
        }

        $this->getOwnedShop($ownerId, $service->shopId);

        return $service;
    }

    /**
     * Validate service details.
     */
    private function validateServiceData(
        array $data,
        bool $includeStatus = false
    ): array {
        $name = trim((string) ($data['name'] ?? ''));

        $description = isset($data['description'])
            ? trim((string) $data['description'])
            : null;

        $durationMinutes = $data['duration_minutes'] ?? null;
        $price = $data['price'] ?? null;

        if ($name === '') {
            throw new Exception('Service name is required.');
        }

        if (mb_strlen($name) > 255) {
            throw new Exception(
                'Service name cannot exceed 255 characters.'
            );
        }

        if (
            $durationMinutes === null ||
            !is_numeric($durationMinutes) ||
            (float) $durationMinutes <= 0 ||
            (float) $durationMinutes != (int) $durationMinutes
        ) {
            throw new Exception(
                'Duration must be a positive whole number of minutes.'
            );
        }

        if (
            $price === null ||
            !is_numeric($price) ||
            !is_finite((float) $price) ||
            (float) $price < 0
        ) {
            throw new Exception(
                'Price must be a valid non-negative number.'
            );
        }

        if (
            $description !== null &&
            mb_strlen($description) > 5000
        ) {
            throw new Exception(
                'Service description cannot exceed 5000 characters.'
            );
        }

        $validated = [
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'duration_minutes' => (int) $durationMinutes,
            'price' => (float) $price,
        ];

        if (array_key_exists('barber_id', $data)) {
            if (
                $data['barber_id'] === null ||
                $data['barber_id'] === ''
            ) {
                $validated['barber_id'] = null;
            } elseif (
                filter_var(
                    $data['barber_id'],
                    FILTER_VALIDATE_INT
                ) !== false &&
                (int) $data['barber_id'] > 0
            ) {
                $validated['barber_id'] = (int) $data['barber_id'];
            } else {
                throw new Exception(
                    'Barber ID must be a positive integer or null.'
                );
            }
        }

        if ($includeStatus) {
            $status = $data['status'] ?? 'active';

            if (!in_array($status, ['active', 'inactive'], true)) {
                throw new Exception(
                    'Invalid status. Use active or inactive.'
                );
            }

            $validated['status'] = $status;
        }

        return $validated;
    }

    /**
     * List services for a shop owned by the authenticated owner.
     */
    public function getServices(
        int $ownerId,
        int $shopId
    ): array {
        $this->getOwnedShop($ownerId, $shopId);

        return $this->serviceRepository->findByShopId($shopId);
    }

    /**
     * Create a service for an owned shop.
     */
    public function createService(
        int $ownerId,
        array $data
    ): int {
        $shopId = (int) ($data['shop_id'] ?? 0);

        $this->getOwnedShop($ownerId, $shopId);

        $validated = $this->validateServiceData($data);

        $validated['shop_id'] = $shopId;

        if (!array_key_exists('barber_id', $validated)) {
            $validated['barber_id'] = null;
        }

        if (!$this->serviceRepository->create($validated)) {
            throw new Exception('Failed to create service.');
        }

        $serviceId = (int) $this->serviceRepository->getLastInsertId();

        if ($serviceId <= 0) {
            throw new Exception('Service was created but its ID could not be retrieved.');
        }

        return $serviceId;
    }

    /**
     * Update a service belonging to an owned shop.
     */
    public function updateService(
        int $ownerId,
        int $serviceId,
        array $data
    ): bool {
        $service = $this->getOwnedService($ownerId, $serviceId);

        $validated = $this->validateServiceData(
            $data,
            true
        );

        if (!array_key_exists('barber_id', $validated)) {
            $validated['barber_id'] = $service->barberId;
        }

        return $this->serviceRepository->update(
            $serviceId,
            $validated
        );
    }

    /**
     * Delete a service belonging to an owned shop.
     */
    public function deleteService(
        int $ownerId,
        int $serviceId
    ): bool {
        $this->getOwnedService($ownerId, $serviceId);

        return $this->serviceRepository->delete($serviceId);
    }
}