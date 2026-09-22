<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use App\Models\BarberHomeService;
use PDO;

class BarberHomeServiceRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection('booking_db');
    }


     //Create a home service
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO barber_home_services (
                barber_id,
                name,
                description,
                duration_minutes,
                price,
                status
            )
            VALUES (
                :barber_id,
                :name,
                :description,
                :duration_minutes,
                :price,
                'active'
            )
        ");

        $stmt->execute([
            'barber_id' => $data['barber_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'duration_minutes' => $data['duration_minutes'],
            'price' => $data['price'],
        ]);

        return (int) $this->db->lastInsertId();
    }


      //Find a home service by ID
    public function findById(int $id): ?BarberHomeService
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM barber_home_services
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return new BarberHomeService($data);
    }


     //Get all home services belonging to a barber
    public function findByBarberId(int $barberId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM barber_home_services
            WHERE barber_id = :barber_id
            ORDER BY created_at DESC
        ");

        $stmt->execute([
            'barber_id' => $barberId,
        ]);

        $services = [];

        foreach ($stmt->fetchAll() as $data) {
            $services[] = new BarberHomeService($data);
        }

        return $services;
    }


     //Update a home service
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE barber_home_services
            SET
                name = :name,
                description = :description,
                duration_minutes = :duration_minutes,
                price = :price,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'duration_minutes' => $data['duration_minutes'],
            'price' => $data['price'],
        ]);
    }


     // Update service status
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare("
            UPDATE barber_home_services
            SET
                status = :status,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'status' => $status,
        ]);
    }
}