<?php

namespace Api\Controllers;

use App\Services\BookingService;
use App\Repositories\ShopRepository;
use App\Repositories\BarberRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\AppointmentRepository;
use App\Repositories\BarberScheduleRepository;
use App\Helpers\JwtHelper;

class BookingController
{
    private BookingService $bookingService;

    public function __construct()
    {
        $this->bookingService = new BookingService(
            new ShopRepository(),
            new BarberRepository(),
            new ServiceRepository(),
            new AppointmentRepository(),
            new BarberScheduleRepository()
        );
    }

    public function bookAppointment()
    {
        header('Content-Type: application/json; charset=UTF-8');

        /*
         * ---------------------------------------------------------
         * Get authenticated customer from JWT
         * ---------------------------------------------------------
         */

        $customerId = $this->getAuthenticatedUserId();

        if ($customerId === null) {
            http_response_code(401);

            echo json_encode([
                'status' => 'error',
                'message' => 'Authentication required.'
            ]);

            return;
        }

        /*
         * ---------------------------------------------------------
         * Read request body
         * ---------------------------------------------------------
         */

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

    /**
     * Extract authenticated user ID from JWT.
     */
    private function getAuthenticatedUserId(): ?int
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
            return null;
        }

        if (!preg_match(
            '/^Bearer\s+(.+)$/i',
            trim($authorizationHeader),
            $matches
        )) {
            return null;
        }

        $token = trim($matches[1]);

        $payload = JwtHelper::decode($token);

        if ($payload === null) {
            return null;
        }

        $userId = $payload['user_id'] ?? null;

        if (
            $userId === null ||
            !is_numeric($userId)
        ) {
            return null;
        }

        return (int) $userId;
    }
}