<?php

namespace Api\Controllers\Barber;

use Api\Middleware\AuthMiddleware;
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

        $auth = AuthMiddleware::checkRole(['BARBER']);

        $userId = (int) $auth['user']->id;

        try {
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

        $auth = AuthMiddleware::checkRole(['BARBER']);

        $userId = (int) $auth['user']->id;

        try {
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

        $auth = AuthMiddleware::checkRole(['BARBER']);

        $userId = (int) $auth['user']->id;

        try {
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

        $auth = AuthMiddleware::checkRole(['BARBER']);

        $userId = (int) $auth['user']->id;

        try {
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
}