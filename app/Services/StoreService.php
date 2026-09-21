<?php

namespace App\Services;

use App\Repositories\ProductRepository;
use App\Repositories\OrderRepository;

class StoreService {
    private ProductRepository $productRepository;
    private OrderRepository $orderRepository;

    public function __construct(ProductRepository $productRepository, OrderRepository $orderRepository) {
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
    }

    public function listProducts(): array {
        return $this->productRepository->findAll();
    }

    public function createOrder(array $data) {
        $this->orderRepository->create($data);
        // ... (handle order items, validation, etc.)
    }
}
