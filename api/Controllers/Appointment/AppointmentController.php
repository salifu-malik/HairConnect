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




    private function getHttpStatusCode(Throwable $e): int
    {
        $message = $e->getMessage();

        // Resource not found
        if (in_array($message, [
            'Appointment not found.',
            'Barber profile not found.',
        ], true)) {
            return 404;
        }

        // Authorization failures
        if (in_array($message, [
            'You are not authorized to check in this appointment.',
            'You are not authorized to start this appointment.',
            'You are not authorized to complete this appointment.',
            'You are not authorized to confirm this appointment.',
        ], true)) {
            return 403;
        }

        // Invalid appointment states and scheduling conflicts
        if (in_array($message, [
            'Only pending or confirmed appointments can be cancelled.',
            'Only pending appointments can be confirmed.',
            'Only confirmed appointments can be checked in.',
            'Completed appointments cannot be started again.',
            'Appointment has already been started.',
            'Only appointments in progress can be completed.',
            'Shop appointments must be checked in before they can be started.',
            'Only confirmed home appointments can be started.',
            'The appointment cannot start before its scheduled date and time.',
            'Check-in is only allowed on the appointment date.',
            'The barber is already booked during the selected time.',
            'The selected time conflicts with another appointment.',
            'Appointment is not awaiting confirmation.',
            'Appointment is not awaiting check-in.',
        ], true)) {
            return 409;
        }

        // Invalid request data or credentials
        if ($e instanceof \InvalidArgumentException || in_array($message, [
                'Invalid booking code.',
                'Only shop appointments can be checked in.',
                'This appointment is not associated with a shop.',
                'Invalid appointment service location.',
            ], true)) {
            return 400;
        }

        // Fallback for errors not yet assigned a more specific status
        return 400;
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
            http_response_code($this->getHttpStatusCode($e));

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

            $input = json_decode(
                file_get_contents('php://input'),
                true
            );

            if (!is_array($input)) {
                throw new \InvalidArgumentException(
                    'Invalid request data.'
                );
            }

            $bookingCode = trim(
                (string) ($input['booking_code'] ?? '')
            );

            if (!preg_match('/^\d{5}$/D', $bookingCode)) {
                throw new \InvalidArgumentException(
                    'A valid five-digit booking code is required.'
                );
            }

            $this->appointmentService->checkInAppointment(
                $appointmentId,
                $userId,
                $bookingCode
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Customer checked in successfully.'
            ]);

        } catch (Throwable $e) {
            http_response_code($this->getHttpStatusCode($e));

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

            $userId = (int)$auth['user']->id;

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
            http_response_code($this->getHttpStatusCode($e));

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
            http_response_code($this->getHttpStatusCode($e));

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
            $message = $e->getMessage();

            if ($message === 'You are not authorized to cancel this appointment.') {
                http_response_code(403);
            } elseif (
                $message === 'Only pending or confirmed appointments can be cancelled.'
            ) {
                http_response_code(409);
            } elseif ($message === 'Appointment not found.') {
                http_response_code(404);
            } else {
                http_response_code(400);
            }

            echo json_encode([
                'status' => 'error',
                'message' => $message
            ]);
        }

    }
}