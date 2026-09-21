<?php

namespace Api\Controllers\Barber;

use App\Helpers\JwtHelper;
use App\Repositories\BarberHomeServiceRepository;
use App\Repositories\BarberRepository;
use App\Services\BarberHomeServiceService;
use Exception;

class HomeServiceController
{
    private BarberHomeServiceService $homeServiceService;

    public function __construct()
    {
        $this->homeServiceService = new BarberHomeServiceService(
            new BarberHomeServiceRepository(),
            new BarberRepository()
        );
    }

    //POST /api/barber/home-services
    public function create(): void
    {
        try {
            $userId = $this->getAuthenticatedUserId();

            $input = json_decode(
                file_get_contents('php://input'),
                true
            );

            if (!is_array($input)) {
                throw new Exception('Invalid JSON request body.');
            }

            $serviceId = $this->homeServiceService->create(
                $userId,
                $input
            );

            http_response_code(201);

            echo json_encode([
                'status' => 'success',
                'message' => 'Home service created successfully.',
                'data' => [
                    'service_id' => $serviceId
                ]
            ]);

        } catch (Exception $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }


     //GET /api/barber/home-services
    public function getMyServices(): void
    {
        try {
            $userId = $this->getAuthenticatedUserId();

            $services = $this->homeServiceService->getMyServices(
                $userId
            );

            $data = [];

            foreach ($services as $service) {
                $data[] = [
                    'id' => $service->id,
                    'barber_id' => $service->barberId,
                    'name' => $service->name,
                    'description' => $service->description,
                    'duration_minutes' => $service->durationMinutes,
                    'price' => $service->price,
                    'status' => $service->status,
                    'created_at' => $service->createdAt,
                    'updated_at' => $service->updatedAt,
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


    //PUT /api/barber/home-services/{id}
    public function update(int $serviceId): void
    {
        try {
            $userId = $this->getAuthenticatedUserId();

            $input = json_decode(
                file_get_contents('php://input'),
                true
            );

            if (!is_array($input)) {
                throw new Exception('Invalid JSON request body.');
            }

            $this->homeServiceService->update(
                $userId,
                $serviceId,
                $input
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Home service updated successfully.'
            ]);

        } catch (Exception $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }


     // PATCH /api/barber/home-services/{id}/status
    public function updateStatus(int $serviceId): void
    {
        try {
            $userId = $this->getAuthenticatedUserId();

            $input = json_decode(
                file_get_contents('php://input'),
                true
            );

            if (!is_array($input)) {
                throw new Exception('Invalid JSON request body.');
            }

            $status = $input['status'] ?? null;

            if (!$status) {
                throw new Exception('Status is required.');
            }

            $this->homeServiceService->updateStatus(
                $userId,
                $serviceId,
                $status
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Home service status updated successfully.'
            ]);

        } catch (Exception $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }


     //Get authenticated user ID from JWT
    private function getAuthenticatedUserId(): int
    {
        $headers = getallheaders();

        $authorization = $headers['Authorization']
            ?? $headers['authorization']
            ?? null;

        if (!$authorization) {
            throw new Exception('Authorization token required.');
        }

        if (!preg_match('/Bearer\s+(.+)/i', $authorization, $matches)) {
            throw new Exception('Invalid authorization header.');
        }

        $token = trim($matches[1]);

        $payload = JwtHelper::decode($token);

        if (!$payload || !isset($payload['user_id'])) {
            throw new Exception('Invalid or expired token.');
        }

        return (int) $payload['user_id'];
    }
}