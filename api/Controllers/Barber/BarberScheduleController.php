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


    //Get the authenticated barber's schedule.
    public function getMySchedule(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $userId = $this->getAuthenticatedUserId();

            $schedules = $this->scheduleService->getMySchedule(
                $userId
            );

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


     //Create a schedule for the authenticated barber.
    public function create(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $userId = $this->getAuthenticatedUserId();

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            if (!is_array($data)) {
                throw new Exception(
                    'Invalid request data.'
                );
            }

            $this->scheduleService->create(
                $userId,
                $data
            );

            http_response_code(201);

            echo json_encode([
                'status' => 'success',
                'message' =>
                    'Barber schedule created successfully.',
            ]);
        } catch (Exception $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }


    //Update a schedule belonging to the authenticated barber.
    public function update(int $scheduleId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $userId = $this->getAuthenticatedUserId();

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            if (!is_array($data)) {
                throw new Exception(
                    'Invalid request data.'
                );
            }

            $this->scheduleService->update(
                $userId,
                $scheduleId,
                $data
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' =>
                    'Barber schedule updated successfully.',
            ]);
        } catch (Exception $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }


     //Delete a schedule belonging to the authenticated barber.
    public function delete(int $scheduleId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $userId = $this->getAuthenticatedUserId();

            $this->scheduleService->delete(
                $userId,
                $scheduleId
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' =>
                    'Barber schedule deleted successfully.',
            ]);
        } catch (Exception $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }


     //Get authenticated user ID from JWT.
    private function getAuthenticatedUserId(): int
    {
        $authorizationHeader =
            $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (
            $authorizationHeader === ''
            && function_exists('getallheaders')
        ) {
            $headers = getallheaders();

            $authorizationHeader =
                $headers['Authorization']
                ?? $headers['authorization']
                ?? '';
        }

        if ($authorizationHeader === '') {
            throw new Exception(
                'Authorization token is required.'
            );
        }

        if (!preg_match(
            '/^Bearer\s+(.+)$/i',
            trim($authorizationHeader),
            $matches
        )) {
            throw new Exception(
                'Invalid authorization header.'
            );
        }

        $token = trim($matches[1]);

        $payload = JwtHelper::decode($token);

        if ($payload === null) {
            throw new Exception(
                'Invalid or expired token.'
            );
        }

        if (!isset($payload['user_id'])) {
            throw new Exception(
                'Invalid token payload.'
            );
        }

        return (int) $payload['user_id'];
    }
}
