<?php

namespace Api\Controllers\Barber;

use Api\Middleware\AuthMiddleware;
use App\Repositories\BarberRepository;
use App\Repositories\ShopRepository;
use App\Repositories\UserRepository;
use App\Services\BarberService;
use App\Helpers\SubscriptionGuard;

class BarberController
{
    private BarberService $barberService;

    public function __construct()
    {
        $this->barberService = new BarberService(
            new BarberRepository(),
            new ShopRepository(),
            new UserRepository()
        );
    }


     //Get all barbers belonging to the authenticated shop owner.
    public function getMyBarbers(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $auth = AuthMiddleware::checkRole(['SHOP_OWNER']);

        $ownerId = (int) $auth['user']->id;

        try {
            $barbers = $this->barberService->getMyBarbers(
                $ownerId
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $barbers
            ]);

        } catch (\Exception $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }


    // Approve or reject a barber application.
    public function updateApproval(int $barberId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $auth = AuthMiddleware::checkRole(['SHOP_OWNER']);

        $ownerId = (int) $auth['user']->id;

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

        $approvalStatus = trim(
            (string) ($data['approval_status'] ?? '')
        );

        if ($approvalStatus === '') {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => 'Approval status is required.'
            ]);

            return;
        }

        try {
            $this->barberService->updateBarberApproval(
                $ownerId,
                $barberId,
                $approvalStatus
            );

            $message = $approvalStatus === 'approved'
                ? 'Barber approved successfully.'
                : 'Barber application rejected successfully.';

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => $message
            ]);

        } catch (\Exception $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }


    //Create an independent barber profile.
    public function createProfile(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $auth = AuthMiddleware::checkRole(['BARBER']);

        $userId = (int) $auth['user']->id;

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($data)) {
            $data = [];
        }

        try {
            $barberId = $this->barberService->registerIndependentBarber(
                $userId,
                $data
            );

            http_response_code(201);

            echo json_encode([
                'status' => 'success',
                'message' => 'Barber profile created successfully.',
                'data' => [
                    'barber_id' => $barberId
                ]
            ]);

        } catch (\Exception $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }


     //Apply for a shop.
    public function applyToShop(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $auth = AuthMiddleware::checkRole(['BARBER']);

        $userId = (int) $auth['user']->id;

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($data)) {
            $data = [];
        }

        $shopId = (int) ($data['shop_id'] ?? 0);

        try {
            $this->barberService->applyToShop(
                $userId,
                $shopId
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Shop application submitted successfully.'
            ]);

        } catch (\Exception $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }


    public function getBarberAvailability(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        /*
         * Availability is public information about a barber's
         * schedule. We don't need CUSTOMER authentication here.
         */

        $barberId = isset($_GET['barber_id'])
            ? (int) $_GET['barber_id']
            : 0;

        $date = isset($_GET['date'])
            ? trim((string) $_GET['date'])
            : '';

        $serviceLocation = isset($_GET['service_location'])
            ? trim((string) $_GET['service_location'])
            : '';

        try {

            if ($barberId <= 0) {
                throw new \Exception('Barber ID is required.');
            }

            if ($date === '') {
                throw new \Exception('Date is required.');
            }

            if ($serviceLocation === '') {
                throw new \Exception(
                    'Service location is required.'
                );
            }

            $availability = $this->bookingService
                ->getBarberAvailability(
                    $barberId,
                    $date,
                    $serviceLocation
                );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $availability
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