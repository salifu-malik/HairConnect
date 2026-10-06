<?php

namespace Api\Controllers\Booking;

use Api\Middleware\AuthMiddleware;
use App\Repositories\AppointmentRepository;
use App\Repositories\BarberHomeServiceRepository;
use App\Repositories\BarberRepository;
use App\Repositories\BarberScheduleRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\ShopRepository;
use App\Services\BookingService;
use App\Exceptions\BookingConflictException;

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

    /**
     * CUSTOMER
     *
     * Create a new appointment.
     */
    public function bookAppointment(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $auth = AuthMiddleware::checkRole(['CUSTOMER']);
        $customerId = (int) $auth['user']->id;

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
            $bookingCode = $this->bookingService->createAppointment(
                $data,
                $customerId
            );

            http_response_code(201);

            echo json_encode([
                'status' => 'success',
                'message' => 'Appointment booked successfully.',
                'data' => [
                    'booking_code' => $bookingCode
                ]
            ]);

        } catch (BookingConflictException $e) {

            http_response_code(409);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);

        } catch (\Throwable $e) {

            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }


    /**
     * CUSTOMER
     *
     * Get appointments belonging to the authenticated customer.
     */
    public function getMyAppointments(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $auth = AuthMiddleware::checkRole(['CUSTOMER']);
        $customerId = (int) $auth['user']->id;

        try {
            $appointments = $this->bookingService
                ->getCustomerAppointments($customerId);

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


    /**
     * SHOP_OWNER
     *
     * Get appointments belonging to the authenticated
     * shop owner's shop.
     */
    public function getShopOwnerAppointments(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $auth = AuthMiddleware::checkRole(['SHOP_OWNER']);
        $ownerId = (int) $auth['user']->id;

        $shopId = isset($_GET['shop_id'])
            ? (int) $_GET['shop_id']
            : 0;

        if ($shopId <= 0) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => 'Shop ID is required.'
            ]);

            return;
        }

        try {
            $appointments = $this->bookingService
                ->getShopOwnerAppointments(
                    $ownerId,
                    $shopId
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


    /**
     * CUSTOMER
     *
     * Return all candidate time slots for a barber,
     * service, location and date.
     *
     * Each slot is returned as:
     *
     * available -> customer can select it
     * booked    -> customer can see it but cannot select it
     */
    public function getAvailableSlots(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        AuthMiddleware::checkRole(['CUSTOMER']);

        $barberId = isset($_GET['barber_id'])
            ? (int) $_GET['barber_id']
            : 0;

        $serviceId = isset($_GET['service_id'])
            ? (int) $_GET['service_id']
            : 0;

        $serviceLocation = isset($_GET['service_location'])
            ? strtoupper(trim((string) $_GET['service_location']))
            : '';

        $date = isset($_GET['date'])
            ? trim((string) $_GET['date'])
            : '';

        if ($barberId <= 0) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => 'Barber ID is required.'
            ]);

            return;
        }

        if ($serviceId <= 0) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => 'Service ID is required.'
            ]);

            return;
        }

        if ($serviceLocation === '') {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => 'Service location is required.'
            ]);

            return;
        }

        if ($date === '') {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => 'Date is required.'
            ]);

            return;
        }

        try {
            $slots = $this->bookingService->getAvailableSlots(
                $barberId,
                $serviceId,
                $serviceLocation,
                $date
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $slots
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