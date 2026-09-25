<?php

namespace Api\Controllers\Booking;

use App\Repositories\BarberRepository;
use App\Repositories\BarberHomeServiceRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\ShopRepository;
use Exception;

class BarberServiceController
{
    private BarberRepository $barberRepository;
    private BarberHomeServiceRepository $homeServiceRepository;
    private ServiceRepository $serviceRepository;
    private ShopRepository $shopRepository;

    public function __construct()
    {
        $this->barberRepository = new BarberRepository();
        $this->homeServiceRepository = new BarberHomeServiceRepository();
        $this->serviceRepository = new ServiceRepository();
        $this->shopRepository = new ShopRepository();
    }

    /**
     * GET /api/barbers/{barberId}/shop-services
     *
     * Get active shop services available to a specific barber.
     */
    public function getShopServices(int $barberId): void
    {
        try {
            $barber = $this->barberRepository->findById($barberId);

            if (!$barber) {
                throw new Exception('Barber not found.');
            }

            if (
                $barber->approvalStatus !== 'approved'
                || $barber->status !== 'active'
            ) {
                throw new Exception(
                    'This barber is not currently available for bookings.'
                );
            }

            if ($barber->shopId === null) {
                throw new Exception(
                    'This barber is an independent barber and does not provide shop services.'
                );
            }

            $shop = $this->shopRepository->findById($barber->shopId);

            if (!$shop) {
                throw new Exception('Barber shop not found.');
            }

            if (
                $shop->approvalStatus !== 'approved'
                || $shop->status !== 'active'
            ) {
                throw new Exception(
                    'This barber shop is not currently available.'
                );
            }

            $services = $this->serviceRepository
                ->findActiveByShopAndBarber(
                    $barber->shopId,
                    $barber->id
                );

            $data = [];

            foreach ($services as $service) {
                $data[] = [
                    'id' => $service->id,
                    'shop_id' => $service->shopId,
                    'barber_id' => $service->barberId,
                    'name' => $service->name,
                    'description' => $service->description,
                    'duration_minutes' => $service->durationMinutes,
                    'price' => $service->price,
                    'status' => $service->status,
                ];
            }

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * GET /api/barbers/{barberId}/home-services
     *
     * Get active home services provided by a specific barber.
     */
    public function getHomeServices(int $barberId): void
    {
        try {
            $barber = $this->barberRepository->findById($barberId);

            if (!$barber) {
                throw new Exception('Barber not found.');
            }

            if (
                $barber->approvalStatus !== 'approved'
                || $barber->status !== 'active'
            ) {
                throw new Exception(
                    'This barber is not currently available for bookings.'
                );
            }

            $services = $this->homeServiceRepository
                ->findByBarberId($barberId);

            $data = [];

            foreach ($services as $service) {
                if ($service->status !== 'active') {
                    continue;
                }

                $data[] = [
                    'id' => $service->id,
                    'barber_id' => $service->barberId,
                    'name' => $service->name,
                    'description' => $service->description,
                    'duration_minutes' => $service->durationMinutes,
                    'price' => $service->price,
                    'status' => $service->status,
                ];
            }

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}