<?php

namespace App\Models;

class Service {
    public int $id;
    public int $shopId;
    public ?int $barberId;
    public string $name;
    public ?string $description;
    public int $durationMinutes;
    public float $price;
    public string $status;

    public function __construct(array $data) {
        $this->id = $data['id'] ?? 0;
        $this->shopId = $data['shop_id'] ?? 0;
        $this->barberId = $data['barber_id'] ?? null;
        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? null;
        $this->durationMinutes = $data['duration_minutes'] ?? 0;
        $this->price = (float)($data['price'] ?? 0.0);
        $this->status = $data['status'] ?? 'active';
    }
}
