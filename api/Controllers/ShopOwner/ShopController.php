<?php

namespace Api\Controllers\ShopOwner;

use Api\Middleware\AuthMiddleware;
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


    //Create a shop.
    public function createShop(): void
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


     //Get shops belonging to the authenticated shop owner.
    public function getMyShops(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $auth = AuthMiddleware::checkRole(['SHOP_OWNER']);

        $ownerId = (int) $auth['user']->id;

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
     *
     * Public endpoint.
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
}