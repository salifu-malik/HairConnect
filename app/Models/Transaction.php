<?php

namespace App\Models;

class Transaction {
    public int $id;
    public int $userId;
    public int $orderId;
    public string $reference;
    public float $amount;
    public string $status;

    public function __construct(array $data) {
        $this->id = $data['id'] ?? 0;
        $this->userId = $data['user_id'] ?? 0;
        $this->orderId = $data['order_id'] ?? 0;
        $this->reference = $data['reference'] ?? '';
        $this->amount = (float)($data['amount'] ?? 0.0);
        $this->status = $data['status'] ?? 'pending';
    }
}
