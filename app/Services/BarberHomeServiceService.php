<?php

namespace App\Services;

use App\Repositories\BarberHomeServiceRepository;
use App\Repositories\BarberRepository;
use Exception;

class BarberHomeServiceService
{
    private BarberHomeServiceRepository $homeServiceRepository;
    private BarberRepository $barberRepository;

    public function __construct(
        BarberHomeServiceRepository $homeServiceRepository,
        BarberRepository $barberRepository
    ) {
        $this->homeServiceRepository = $homeServiceRepository;
        $this->barberRepository = $barberRepository;
    }


     //Make sure the user has an active barber profile

    private function getActiveBarber(int $userId)
    {
        $barber = $this->barberRepository->findByUserId($userId);

        if (!$barber) {
            throw new Exception(
                'You must create a barber profile before managing home services.'
            );
        }

        if ($barber->approvalStatus !== 'approved') {
            throw new Exception(
                'Your barber profile has not been approved.'
            );
        }

        if ($barber->status !== 'active') {
            throw new Exception(
                'Your barber profile is not active.'
            );
        }

        return $barber;
    }


      //Create a home service

    public function create(int $userId, array $data): int
    {
        $barber = $this->getActiveBarber($userId);

        $name = trim($data['name'] ?? '');
        $description = isset($data['description'])
            ? trim($data['description'])
            : null;

        $durationMinutes = $data['duration_minutes'] ?? null;
        $price = $data['price'] ?? null;

        if ($name === '') {
            throw new Exception('Service name is required.');
        }

        if (strlen($name) > 255) {
            throw new Exception(
                'Service name cannot exceed 255 characters.'
            );
        }

        if ($durationMinutes === null || !is_numeric($durationMinutes)) {
            throw new Exception(
                'Duration in minutes is required.'
            );
        }

        $durationMinutes = (int) $durationMinutes;

        if ($durationMinutes <= 0) {
            throw new Exception(
                'Duration must be greater than zero.'
            );
        }

        if ($price === null || !is_numeric($price)) {
            throw new Exception(
                'Price is required.'
            );
        }

        $price = (float) $price;

        if ($price < 0) {
            throw new Exception(
                'Price cannot be negative.'
            );
        }

        return $this->homeServiceRepository->create([
            'barber_id' => $barber->id,
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'duration_minutes' => $durationMinutes,
            'price' => $price,
        ]);
    }


    // Get all home services belonging to the authenticated barber

    public function getMyServices(int $userId): array
    {
        $barber = $this->getActiveBarber($userId);

        return $this->homeServiceRepository->findByBarberId(
            $barber->id
        );
    }


     //Update a home service belonging to the authenticated barber

    public function update(
        int $userId,
        int $serviceId,
        array $data
    ): bool {
        $barber = $this->getActiveBarber($userId);

        $service = $this->homeServiceRepository->findById($serviceId);

        if (!$service) {
            throw new Exception('Home service not found.');
        }

        if ($service->barberId !== $barber->id) {
            throw new Exception(
                'You are not allowed to modify this home service.'
            );
        }

        $name = trim($data['name'] ?? '');
        $description = isset($data['description'])
            ? trim($data['description'])
            : null;

        $durationMinutes = $data['duration_minutes'] ?? null;
        $price = $data['price'] ?? null;

        if ($name === '') {
            throw new Exception('Service name is required.');
        }

        if (strlen($name) > 255) {
            throw new Exception(
                'Service name cannot exceed 255 characters.'
            );
        }

        if ($durationMinutes === null || !is_numeric($durationMinutes)) {
            throw new Exception(
                'Duration in minutes is required.'
            );
        }

        $durationMinutes = (int) $durationMinutes;

        if ($durationMinutes <= 0) {
            throw new Exception(
                'Duration must be greater than zero.'
            );
        }

        if ($price === null || !is_numeric($price)) {
            throw new Exception(
                'Price is required.'
            );
        }

        $price = (float) $price;

        if ($price < 0) {
            throw new Exception(
                'Price cannot be negative.'
            );
        }

        return $this->homeServiceRepository->update(
            $serviceId,
            [
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'duration_minutes' => $durationMinutes,
                'price' => $price,
            ]
        );
    }


     //Activate or deactivate a home service

    public function updateStatus(
        int $userId,
        int $serviceId,
        string $status
    ): bool {
        $barber = $this->getActiveBarber($userId);

        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new Exception(
                'Invalid status. Use active or inactive.'
            );
        }

        $service = $this->homeServiceRepository->findById($serviceId);

        if (!$service) {
            throw new Exception('Home service not found.');
        }

        if ($service->barberId !== $barber->id) {
            throw new Exception(
                'You are not allowed to modify this home service.'
            );
        }

        return $this->homeServiceRepository->updateStatus(
            $serviceId,
            $status
        );
    }
}