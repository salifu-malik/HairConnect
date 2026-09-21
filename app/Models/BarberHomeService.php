<?php

namespace App\Models;

class BarberHomeService
{
    public int $id;
    public int $barberId;
    public string $name;
    public ?string $description;
    public int $durationMinutes;
    public float $price;
    public string $status;
    public ?string $createdAt;
    public ?string $updatedAt;

    public function __construct(array $data)
    {
        $this->id = (int) ($data['id'] ?? 0);
        $this->barberId = (int) ($data['barber_id'] ?? 0);
        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? null;
        $this->durationMinutes = (int) ($data['duration_minutes'] ?? 0);
        $this->price = (float) ($data['price'] ?? 0);
        $this->status = $data['status'] ?? 'active';
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }
}