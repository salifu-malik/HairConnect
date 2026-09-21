<?php

namespace Api\Controllers;

use App\Services\BarberService;
use App\Repositories\BarberRepository;
use App\Repositories\ShopRepository;
use App\Repositories\UserRepository;
use App\Helpers\JwtHelper;

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

    /**
     * Get all barbers belonging to the authenticated shop owner.
     */
    public function getMyBarbers(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $ownerId = $this->getAuthenticatedUserId();

        if ($ownerId === null) {
            http_response_code(401);

            echo json_encode([
                'status' => 'error',
                'message' => 'Authentication required.'
            ]);

            return;
        }

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

    /**
     * Approve or reject a barber application.
     */
    public function updateApproval(int $barberId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $ownerId = $this->getAuthenticatedUserId();

        if ($ownerId === null) {
            http_response_code(401);

            echo json_encode([
                'status' => 'error',
                'message' => 'Authentication required.'
            ]);

            return;
        }

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

    /**
     * Get authenticated user ID from JWT.
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

    //Create  Barber profile

    public function createProfile(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $userId = $this->getAuthenticatedUserId();

        if ($userId === null) {
            http_response_code(401);

            echo json_encode([
                'status' => 'error',
                'message' => 'Authentication required.'
            ]);

            return;
        }

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

}
