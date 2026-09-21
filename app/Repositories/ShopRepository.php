<?php

namespace App\Repositories;

use App\Models\Shop;
use App\Helpers\DatabaseManager;
use PDO;

class ShopRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection('booking_db');
    }

    /**
     * Create a new shop.
     *
     * New shops start as:
     * - approval_status = pending
     * - status = inactive
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO shops (
                owner_id,
                name,
                location,
                description,
                approval_status,
                status
            )
            VALUES (
                :owner_id,
                :name,
                :location,
                :description,
                'pending',
                'inactive'
            )
        ");

        $stmt->execute([
            'owner_id' => $data['owner_id'],
            'name' => $data['name'],
            'location' => $data['location'],
            'description' => $data['description'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Find a shop by ID.
     */
    public function findById(int $id): ?Shop
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM shops
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $data = $stmt->fetch();

        return $data
            ? new Shop($data)
            : null;
    }

    /**
     * Find all shops owned by a user.
     */
    public function findByOwnerId(int $ownerId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM shops
            WHERE owner_id = :owner_id
            ORDER BY created_at DESC
        ");

        $stmt->execute([
            'owner_id' => $ownerId,
        ]);

        $shops = [];

        foreach ($stmt->fetchAll() as $data) {
            $shops[] = new Shop($data);
        }

        return $shops;
    }

    /**
     * Find all approved and active shops.
     *
     * This will later be useful when a barber
     * wants to select a shop to work under.
     */
    public function findApprovedActiveShops(): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM shops
            WHERE approval_status = 'approved'
              AND status = 'active'
            ORDER BY name ASC
        ");

        $stmt->execute();

        $shops = [];

        foreach ($stmt->fetchAll() as $data) {
            $shops[] = new Shop($data);
        }

        return $shops;
    }

    /**
     * Update shop approval status.
     */
    public function updateApprovalStatus(
        int $shopId,
        string $approvalStatus,
        ?int $approvedBy = null
    ): bool {
        if ($approvalStatus === 'approved') {
            $stmt = $this->db->prepare("
                UPDATE shops
                SET
                    approval_status = 'approved',
                    status = 'active',
                    approved_at = CURRENT_TIMESTAMP,
                    approved_by = :approved_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");

            return $stmt->execute([
                'id' => $shopId,
                'approved_by' => $approvedBy,
            ]);
        }

        if ($approvalStatus === 'rejected') {
            $stmt = $this->db->prepare("
                UPDATE shops
                SET
                    approval_status = 'rejected',
                    status = 'inactive',
                    approved_at = NULL,
                    approved_by = :approved_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");

            return $stmt->execute([
                'id' => $shopId,
                'approved_by' => $approvedBy,
            ]);
        }

        throw new \InvalidArgumentException(
            'Invalid shop approval status.'
        );
    }

    //Find pending shops

    public function findPendingShops(): array
    {
        $stmt = $this->db->prepare("
        SELECT *
        FROM shops
        WHERE approval_status = 'pending'
        ORDER BY created_at ASC
    ");

        $stmt->execute();

        $shops = [];

        foreach ($stmt->fetchAll() as $data) {
            $shops[] = new Shop($data);
        }

        return $shops;
    }
}