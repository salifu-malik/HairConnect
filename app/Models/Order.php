<?php

namespace App\Models;

class Order {
    public int $id;
    public int $customerId;
    public float $totalAmount;
    public string $status;

    public function __construct(array $data) {
        $this->id = $data['id'] ?? 0;
        $this->customerId = $data['customer_id'] ?? 0;
        $this->totalAmount = (float)($data['total_amount'] ?? 0.0);
        $this->status = $data['status'] ?? 'pending';
    }
}
