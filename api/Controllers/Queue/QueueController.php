<?php

namespace Api\Controllers\Queue;

use Api\Middleware\AuthMiddleware;
use App\Repositories\QueueRepository;
use App\Repositories\ShopRepository;
use App\Services\QueueService;

class QueueController
{
    private QueueService $queueService;

    public function __construct()
    {
        $this->queueService = new QueueService(
            new QueueRepository(),
            new ShopRepository()
        );
    }

    /**
     * Join a shop's walk-in queue.
     */
    public function joinQueue(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        /*
         * Authenticate the caller and require CUSTOMER role.
         *
         * The customer ID comes from the authenticated user,
         * not from the request body.
         */
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

        if (
            !isset($data['shop_id']) ||
            !is_numeric($data['shop_id'])
        ) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => 'Shop ID is required.'
            ]);

            return;
        }

        try {
            $queue = $this->queueService->joinQueue(
                $customerId,
                (int) $data['shop_id']
            );

            http_response_code(201);

            echo json_encode([
                'status' => 'success',
                'message' => 'You have joined the queue successfully.',
                'data' => $queue
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
     * Get the authenticated customer's active queue at a shop.
     */
    public function getMyQueue(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        /*
         * Authenticate the caller and require CUSTOMER role.
         */
        $auth = AuthMiddleware::checkRole(['CUSTOMER']);

        $customerId = (int) $auth['user']->id;

        $shopId = $_GET['shop_id'] ?? null;

        if (
            $shopId === null ||
            !is_numeric($shopId)
        ) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => 'Shop ID is required.'
            ]);

            return;
        }

        try {
            $queue = $this->queueService->getMyQueue(
                $customerId,
                (int) $shopId
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $queue
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