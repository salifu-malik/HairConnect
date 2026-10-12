<?php

use Api\Middleware\AuthMiddleware;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Services\StoreService;

class StoreController
{
    private StoreService $storeService;

    public function __construct()
    {
        $this->storeService = new StoreService(
            new ProductRepository(),
            new OrderRepository()
        );
    }

    public function getProducts(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $products = $this->storeService->listProducts();

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $products,
            ]);
        } catch (\Throwable $e) {
            error_log('StoreController::getProducts: ' . $e->getMessage());

            http_response_code(500);

            echo json_encode([
                'status' => 'error',
                'message' => 'Unable to retrieve products.',
            ]);
        }
    }

    public function placeOrder(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            if (!is_array($data)) {
                http_response_code(400);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid request data.',
                ]);

                return;
            }

            $this->storeService->createOrder($data);

            http_response_code(201);

            echo json_encode([
                'status' => 'success',
                'message' => 'Order placed',
            ]);
        } catch (\Throwable $e) {
            error_log('StoreController::placeOrder: ' . $e->getMessage());

            http_response_code(500);

            echo json_encode([
                'status' => 'error',
                'message' => 'Unable to place order.',
            ]);
        }
    }

    /**
     * Retrieve orders belonging to the authenticated customer.
     */
    public function getCustomerOrders(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $auth = AuthMiddleware::checkRole(['CUSTOMER']);

        $customerId = (int) $auth['user']->id;

        try {
            $orders = $this->storeService->getCustomerOrders(
                $customerId
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $orders,
            ]);
        } catch (\Throwable $e) {
            error_log(
                'StoreController::getCustomerOrders: ' .
                $e->getMessage()
            );

            http_response_code(500);

            echo json_encode([
                'status' => 'error',
                'message' => 'Unable to retrieve your orders.',
            ]);
        }
    }
}