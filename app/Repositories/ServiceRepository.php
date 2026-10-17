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

    public function create(array $data): int
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

        $stmt->execute([
            'shop_id' => $data['shop_id'],
            'barber_id' => $data['barber_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'duration_minutes' => $data['duration_minutes'],
            'price' => $data['price'],
        ]);

        return (int) $this->db->lastInsertId();
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

    /**
     * Get active shop services available to a specific barber.
     *
     * A service is available when:
     * - it belongs to the barber's shop
     * - it is active
     * - it is either assigned to this barber specifically
     *   or available to all barbers in the shop
     */
    public function findActiveByShopAndBarber(
        int $shopId,
        int $barberId
    ): array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM services
            WHERE shop_id = :shop_id
              AND status = 'active'
              AND (
                  barber_id IS NULL
                  OR barber_id = :barber_id
              )
            ORDER BY name ASC
        ");

        $stmt->execute([
            'shop_id' => $shopId,
            'barber_id' => $barberId,
        ]);

        $services = [];

        foreach ($stmt->fetchAll() as $data) {
            $services[] = new Service($data);
        }

        return $services;
    }


    /**
     * Get all services belonging to a specific shop.
     *
     * Includes both active and inactive services.
     */
    public function findByShopId(int $shopId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM services
            WHERE shop_id = :shop_id
            ORDER BY created_at DESC
        ");

        $stmt->execute([
            'shop_id' => $shopId,
        ]);

        $services = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $data) {
            $services[] = new Service($data);
        }

        return $services;
    }

    /**
     * Update an existing service.
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE services
            SET
                barber_id = :barber_id,
                name = :name,
                description = :description,
                duration_minutes = :duration_minutes,
                price = :price,
                status = :status
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'barber_id' => $data['barber_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'duration_minutes' => $data['duration_minutes'],
            'price' => $data['price'],
            'status' => $data['status'] ?? 'active',
        ]);
    }

    /**
     * Delete a service by its ID.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM services
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
        ]);
    }


}