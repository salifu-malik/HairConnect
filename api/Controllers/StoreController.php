<?php

namespace Api\Controllers;

use App\Services\StoreService;
use App\Repositories\ProductRepository;
use App\Repositories\OrderRepository;

class StoreController {
    private StoreService $storeService;

    public function __construct() {
        $this->storeService = new StoreService(
            new ProductRepository(),
            new OrderRepository()
        );
    }

    public function getProducts() {
        $products = $this->storeService->listProducts();
        echo json_encode(['status' => 'success', 'data' => $products]);
    }

    public function placeOrder() {
        $data = json_decode(file_get_contents('php://input'), true);
        $this->storeService->createOrder($data);
        echo json_encode(['status' => 'success', 'message' => 'Order placed']);
    }
}
