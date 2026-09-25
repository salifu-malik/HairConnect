<?php

namespace App\Models;

class Plan
{
    public int $id;
    public string $name;
    public string $role;
    public ?array $features;
    public string $createdAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->name = $data['name'];
        $this->role = $data['role'];

        $this->features = isset($data['features'])
            ? json_decode($data['features'], true)
            : null;

        $this->createdAt = $data['created_at'];
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'features' => $this->features,
            'created_at' => $this->createdAt,
        ];
    }
}