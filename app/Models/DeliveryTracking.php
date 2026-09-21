<?php

namespace App\Models;

class DeliveryTracking {
    public int $id;
    public int $deliveryId;
    public float $latitude;
    public float $longitude;
    public string $status;
    public string $timestamp;

    public function __construct(array $data) {
        $this->id = $data['id'] ?? 0;
        $this->deliveryId = $data['delivery_id'] ?? 0;
        $this->latitude = (float)($data['latitude'] ?? 0.0);
        $this->longitude = (float)($data['longitude'] ?? 0.0);
        $this->status = $data['status'] ?? '';
        $this->timestamp = $data['timestamp'] ?? '';
    }
}
