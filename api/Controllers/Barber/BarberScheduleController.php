<?php

namespace Api\Controllers\Barber;

use App\Helpers\JwtHelper;
use App\Repositories\BarberRepository;
use App\Repositories\BarberScheduleRepository;
use App\Services\BarberScheduleService;
use Exception;

class BarberScheduleController
{
    private BarberScheduleService $scheduleService;

    public function __construct()
    {
        $scheduleRepository = new BarberScheduleRepository();
        $barberRepository = new BarberRepository();

        $this->scheduleService = new BarberScheduleService(
            $scheduleRepository,
            $barberRepository
        );
    }


      //Get the authenticated barber's schedule
    public function getMySchedule(): void
    {
        try {
            $userId = $this->getAuthenticatedUserId();

            $schedules = $this->scheduleService->getMySchedule($userId);

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $schedules,
            ]);
        } catch (Exception $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

     //Create a schedule for the authenticated barber
    public function create(): void
    {
        try {
            $userId = $this->getAuthenticatedUserId();

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            if (!is_array($data)) {
                throw new Exception('Invalid request data.');
            }

            $this->scheduleService->create(
                $userId,
                $data
            );

            http_response_code(201);

            echo json_encode([
                'status' => 'success',
                'message' => 'Barber schedule created successfully.',
            ]);
        } catch (Exception $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

      //Get authenticated user ID from JWT
    private function getAuthenticatedUserId(): int
    {
        $headers = getallheaders();

        $authHeader =
            $headers['Authorization']
            ?? $headers['authorization']
            ?? null;

        if (!$authHeader) {
            throw new Exception(
                'Authorization token is required.'
            );
        }

        if (!preg_match(
            '/Bearer\s+(.+)/i',
            $authHeader,
            $matches
        )) {
            throw new Exception(
                'Invalid authorization header.'
            );
        }

        $token = trim($matches[1]);

        $payload = JwtHelper::decode($token);

        if (!$payload || !isset($payload['sub'])) {
            throw new Exception(
                'Invalid or expired token.'
            );
        }

        return (int) $payload['sub'];
    }
}
