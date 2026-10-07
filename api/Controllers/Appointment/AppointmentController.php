<?php



namespace Api\Controllers\Appointment;

use Api\Middleware\AuthMiddleware;
use App\Repositories\AppointmentRepository;
use App\Repositories\BarberRepository;
use App\Repositories\ShopRepository;
use App\Services\AppointmentService;
use Throwable;

class AppointmentController
{
    private AppointmentService $appointmentService;

    public function __construct()
    {
        $this->appointmentService = new AppointmentService(
            new AppointmentRepository(),
            new BarberRepository(),
            new ShopRepository()
        );
    }




    /**
     * Confirm a pending appointment.
     *
     * BARBER only.
     */
    public function confirmAppointment(int $appointmentId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $auth = AuthMiddleware::checkRole(['BARBER']);

            $userId = (int) $auth['user']->id;

            $this->appointmentService->confirmAppointment(
                $appointmentId,
                $userId
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Appointment confirmed successfully.'
            ]);

        } catch (Throwable $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check in a confirmed shop appointment.
     *
     * SHOP_OWNER only.
     */
    public function checkInAppointment(int $appointmentId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $auth = AuthMiddleware::checkRole(['SHOP_OWNER']);

            $userId = (int) $auth['user']->id;

            $this->appointmentService->checkInAppointment(
                $appointmentId,
                $userId
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Customer checked in successfully.'
            ]);

        } catch (Throwable $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Start a checked-in appointment.
     *
     * BARBER only.
     */
    public function startAppointment(int $appointmentId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $auth = AuthMiddleware::checkRole(['BARBER']);

            $userId = (int) $auth['user']->id;

            $this->appointmentService->startAppointment(
                $appointmentId,
                $userId
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Appointment started successfully.'
            ]);

        } catch (Throwable $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Complete an appointment that is currently in progress.
     *
     * BARBER only.
     */
    public function completeAppointment(int $appointmentId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $auth = AuthMiddleware::checkRole(['BARBER']);

            $userId = (int) $auth['user']->id;

            $this->appointmentService->completeAppointment(
                $appointmentId,
                $userId
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Appointment completed successfully.'
            ]);

        } catch (Throwable $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }


    public function cancelAppointment(int $appointmentId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $auth = AuthMiddleware::checkRole(['CUSTOMER']);
            $userId = (int)$auth['user']->id;

            $this->appointmentService->cancelAppointment(
                $appointmentId,
                $userId
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Appointment cancelled successfully.'
            ]);

        } catch (Throwable $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }


}