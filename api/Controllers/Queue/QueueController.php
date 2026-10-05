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
     * CUSTOMER only.
     */
    public function joinQueue(): void
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
     * CUSTOMER only.
     */
    public function getMyQueue(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

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

    /**
     * Get the active queue for a shop.
     * SHOP_OWNER only.
     */
    public function getShopQueue(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $auth = AuthMiddleware::checkRole(['SHOP_OWNER']);

        $ownerId = (int) $auth['user']->id;

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
            $queue = $this->queueService->getShopQueue(
                $ownerId,
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

    /**
     * Start serving a waiting queue entry.
     * SHOP_OWNER only.
     */
    public function startQueue(int $queueId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $auth = AuthMiddleware::checkRole(['SHOP_OWNER']);

        $ownerId = (int) $auth['user']->id;

        try {
            $queue = $this->queueService->startQueue(
                $ownerId,
                $queueId
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Queue entry started successfully.',
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
     * Complete an in-progress queue entry.
     * SHOP_OWNER only.
     */
    public function completeQueue(int $queueId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $auth = AuthMiddleware::checkRole(['SHOP_OWNER']);

        $ownerId = (int) $auth['user']->id;

        try {
            $queue = $this->queueService->completeQueue(
                $ownerId,
                $queueId
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Queue entry completed successfully.',
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
     * Cancel a waiting queue entry.
     * SHOP_OWNER only.
     */
    public function cancelQueue(int $queueId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $auth = AuthMiddleware::checkRole(['SHOP_OWNER']);

        $ownerId = (int) $auth['user']->id;

        try {
            $queue = $this->queueService->cancelQueue(
                $ownerId,
                $queueId
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Queue entry cancelled successfully.',
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