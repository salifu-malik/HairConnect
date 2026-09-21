<?php

namespace App\Repositories;

use App\Models\Service;
use App\Helpers\DatabaseManager;
use PDO;

class ServiceRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection('booking_db');
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO services (
                shop_id,
                barber_id,
                name,
                description,
                duration_minutes,
                price
            )
            VALUES (
                :shop_id,
                :barber_id,
                :name,
                :description,
                :duration_minutes,
                :price
            )
        ");

        return $stmt->execute([
            'shop_id' => $data['shop_id'],
            'barber_id' => $data['barber_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'duration_minutes' => $data['duration_minutes'],
            'price' => $data['price'],
        ]);
    }

    public function findById(int $id): ?Service
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM services
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id
        ]);

        $data = $stmt->fetch();

        return $data ? new Service($data) : null;
    }
}