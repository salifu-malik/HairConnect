<?php

namespace App\Models;

class Plan {
    public int $id;
    public string $name;
    public string $role;
    public float $price;
    public string $billingPeriod;
    public array $features;

    public function __construct(array $data) {
        $this->id = $data['id'] ?? 0;
        $this->name = $data['name'] ?? '';
        $this->role = $data['role'] ?? '';
        $this->price = (float)($data['price'] ?? 0.0);
        $this->billingPeriod = $data['billing_period'] ?? '';
        $this->features = isset($data['features']) ? json_decode($data['features'], true) : [];
    }
}
