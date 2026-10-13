<?php

namespace Api\Controllers\ShopOwner;

use Api\Middleware\AuthMiddleware;
use App\Repositories\BarberRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\ShopRepository;
use App\Services\ShopOwnerServiceService;
use Throwable;

class ShopServiceController
{
    private ShopOwnerServiceService $service;

    public function __construct()
    {
        $this->service = new ShopOwnerServiceService(
            new ServiceRepository(),
            new ShopRepository(),
            new BarberRepository()
        );
    }

    /**
     * GET /api/shop/services?shop_id={id}
     *
     * Retrieve services belonging to an owned shop.
     */
    public function getServices(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $auth = AuthMiddleware::checkRole(['SHOP_OWNER']);
        $ownerId = (int) $auth['user']->id;

        $shopId = isset($_GET['shop_id'])
            ? (int) $_GET['shop_id']
            : 0;

        try {
            if ($shopId <= 0) {
                throw new \InvalidArgumentException(
                    'Shop ID is required.'
                );
            }

            $services = $this->service->getServices(
                $ownerId,
                $shopId
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
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            $statusCode = $e instanceof \InvalidArgumentException
                ? 400
                : 400;

            http_response_code($statusCode);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * POST /api/shop/services
     *
     * Create a service for an owned shop.
     */
    public function createService(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $auth = AuthMiddleware::checkRole(['SHOP_OWNER']);
        $ownerId = (int) $auth['user']->id;

        try {
            $input = json_decode(
                file_get_contents('php://input'),
                true
            );

            if (!is_array($input)) {
                throw new \InvalidArgumentException(
                    'Invalid JSON request body.'
                );
            }

            $serviceId = $this->service->createService(
                $ownerId,
                $input
            );

            http_response_code(201);

            echo json_encode([
                'status' => 'success',
                'message' => 'Service created successfully.',
                'data' => [
                    'id' => $serviceId,
                ],
            ]);
        } catch (Throwable $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * PUT /api/shop/services/{id}
     *
     * Update a service belonging to an owned shop.
     */
    public function updateService(int $serviceId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $auth = AuthMiddleware::checkRole(['SHOP_OWNER']);
        $ownerId = (int) $auth['user']->id;

        try {
            $input = json_decode(
                file_get_contents('php://input'),
                true
            );

            if (!is_array($input)) {
                throw new \InvalidArgumentException(
                    'Invalid JSON request body.'
                );
            }

            $this->service->updateService(
                $ownerId,
                $serviceId,
                $input
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Service updated successfully.',
            ]);
        } catch (Throwable $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * DELETE /api/shop/services/{id}
     *
     * Delete a service belonging to an owned shop.
     */
    public function deleteService(int $serviceId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $auth = AuthMiddleware::checkRole(['SHOP_OWNER']);
        $ownerId = (int) $auth['user']->id;

        try {
            $this->service->deleteService(
                $ownerId,
                $serviceId
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Service deleted successfully.',
            ]);
        } catch (Throwable $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }
}