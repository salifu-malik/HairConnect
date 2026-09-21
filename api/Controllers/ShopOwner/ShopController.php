<?php


use App\Helpers\JwtHelper;
use App\Repositories\ShopRepository;
use App\Repositories\UserRepository;
use App\Services\ShopService;

class ShopController
{
    private ShopService $shopService;

    public function __construct()
    {
        $this->shopService = new ShopService(
            new ShopRepository(),
            new UserRepository()
        );
    }

    /**
     * Create a shop.
     */
    public function createShop(): void
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

        try {
            $shopId = $this->shopService->createShop(
                $ownerId,
                $data
            );

            http_response_code(201);

            echo json_encode([
                'status' => 'success',
                'message' => 'Shop registration submitted successfully.',
                'data' => [
                    'shop_id' => $shopId,
                    'approval_status' => 'pending',
                    'status' => 'inactive'
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

    /**
     * Get shops belonging to the authenticated shop owner.
     */
    public function getMyShops(): void
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
            $shops = $this->shopService->getMyShops(
                $ownerId
            );

            $data = [];

            foreach ($shops as $shop) {
                $data[] = [
                    'id' => $shop->id,
                    'owner_id' => $shop->ownerId,
                    'name' => $shop->name,
                    'location' => $shop->location,
                    'description' => $shop->description,
                    'approval_status' => $shop->approvalStatus,
                    'approved_at' => $shop->approvedAt,
                    'approved_by' => $shop->approvedBy,
                    'status' => $shop->status
                ];
            }

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $data
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
     * Get shops that are approved and active.
     */
    public function getAvailableShops(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $shops = $this->shopService->getAvailableShops();

            $data = [];

            foreach ($shops as $shop) {
                $data[] = [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'location' => $shop->location,
                    'description' => $shop->description,
                    'approval_status' => $shop->approvalStatus,
                    'status' => $shop->status
                ];
            }

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $data
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