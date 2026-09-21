<?php

namespace App\Models;

class OrderItem {
    public int $id;
    public int $orderId;
    public int $productId;
    public int $quantity;
    public float $price;

    public function __construct(array $data) {
        $this->id = $data['id'] ?? 0;
        $this->orderId = $data['order_id'] ?? 0;
        $this->productId = $data['product_id'] ?? 0;
        $this->quantity = $data['quantity'] ?? 0;
        $this->price = (float)($data['price'] ?? 0.0);
    }
}
