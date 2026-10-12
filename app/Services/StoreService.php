<?php

namespace App\Services;

use App\Repositories\ProductRepository;
use App\Repositories\OrderRepository;

class StoreService
{
    private ProductRepository $productRepository;
    private OrderRepository $orderRepository;

    public function __construct(
        ProductRepository $productRepository,
        OrderRepository $orderRepository
    ) {
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
    }

    public function listProducts(): array
    {
        return $this->productRepository->findAll();
    }

    public function createOrder(array $data)
    {
        $this->orderRepository->create($data);

        // Existing order-item handling and validation
        // still need to be implemented separately.
    }

    /**
     * Retrieve all orders belonging to a customer.
     */
    public function getCustomerOrders(int $customerId): array
    {
        if ($customerId <= 0) {
            throw new \InvalidArgumentException(
                'A valid customer ID is required.'
            );
        }

        return $this->orderRepository->findByCustomerId($customerId);
    }
}