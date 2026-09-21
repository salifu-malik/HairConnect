<?php

namespace App\Models;

class Delivery {
    public int $id;
    public int $orderId;
    public int $deliveryPersonId;
    public string $status;

    public function __construct(array $data) {
        $this->id = $data['id'] ?? 0;
        $this->orderId = $data['order_id'] ?? 0;
        $this->deliveryPersonId = $data['delivery_person_id'] ?? 0;
        $this->status = $data['status'] ?? 'pending';
    }
}
