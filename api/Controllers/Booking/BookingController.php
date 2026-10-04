<?php

namespace Api\Controllers\Booking;

use Api\Middleware\AuthMiddleware;
use App\Repositories\AppointmentRepository;
use App\Repositories\BarberRepository;
use App\Repositories\BarberScheduleRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\ShopRepository;
use App\Services\BookingService;
use App\Repositories\BarberHomeServiceRepository;

class BookingController
{
    private BookingService $bookingService;

    public function __construct()
    {
        $this->bookingService = new BookingService(
            new ShopRepository(),
            new BarberRepository(),
            new ServiceRepository(),
            new BarberHomeServiceRepository(),
            new AppointmentRepository(),
            new BarberScheduleRepository()
        );
    }

    public function bookAppointment(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        /*
         * Authenticate the caller and require CUSTOMER role.
         *
         * The customer ID comes from the authenticated database user,
         * not from the request body.
         */
        $auth = AuthMiddleware::checkRole(['CUSTOMER']);
        $customerId = (int) $auth['user']->id;

        // Read request body
        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($data)) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid request data.'
            ]);

            return;
        }

        try {
            $this->bookingService->createAppointment(
                $data,
                $customerId
            );

            http_response_code(201);

            echo json_encode([
                'status' => 'success',
                'message' => 'Appointment booked successfully.'
            ]);

        } catch (\Exception $e) {

            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }


    public function getMyAppointments(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        /*
         * Authenticate the caller and require CUSTOMER role.
         *
         * The customer ID comes from the authenticated database user,
         * not from the request.
         */
        $auth = AuthMiddleware::checkRole(['CUSTOMER']);
        $customerId = (int) $auth['user']->id;

        try {
            $appointments = $this->bookingService->getCustomerAppointments(
                $customerId
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $appointments
            ]);

        } catch (\Exception $e) {

            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

}