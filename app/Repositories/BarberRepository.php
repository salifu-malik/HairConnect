<?php

namespace App\Repositories;

use App\Models\Barber;
use App\Helpers\DatabaseManager;
use App\Helpers\RedisManager;
use PDO;

class BarberRepository
{
    private PDO $db;
    private RedisManager $redis;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection('booking_db');
        $this->redis = new RedisManager();
    }


     //Invalidate the public barber discovery cache.
    private function invalidateDiscoveryCache(): void
    {
        $this->redis->delete('barbers:discoverable');
    }

    /**
     * Create an independent barber profile.
     *
     * Independent barbers do not belong to a shop.
     * They are immediately active for home services.
     */
    public function createIndependent(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO barbers (
                user_id,
                shop_id,
                experience,
                speciality,
                approval_status,
                status
            )
            VALUES (
                :user_id,
                NULL,
                :experience,
                :speciality,
                'approved',
                'active'
            )
        ");

        $stmt->execute([
            'user_id' => $data['user_id'],
            'experience' => $data['experience'] ?? 0,
            'speciality' => $data['speciality'] ?? null,
        ]);

        $barberId = (int) $this->db->lastInsertId();

        $this->invalidateDiscoveryCache();

        return $barberId;
    }

    /**
     * Create a barber application for a shop.
     *
     * Shop applications start as pending/inactive.
     */
    public function createShopApplication(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO barbers (
                user_id,
                shop_id,
                experience,
                speciality,
                approval_status,
                status
            )
            VALUES (
                :user_id,
                :shop_id,
                :experience,
                :speciality,
                'pending',
                'inactive'
            )
        ");

        $stmt->execute([
            'user_id' => $data['user_id'],
            'shop_id' => $data['shop_id'],
            'experience' => $data['experience'] ?? 0,
            'speciality' => $data['speciality'] ?? null,
        ]);

        $barberId = (int) $this->db->lastInsertId();

        $this->invalidateDiscoveryCache();

        return $barberId;
    }


    //Apply an independent barber to a shop.
    public function applyToShop(
        int $barberId,
        int $shopId
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE barbers
            SET
                shop_id = :shop_id,
                approval_status = 'pending',
                status = 'inactive',
                approved_at = NULL,
                approved_by = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND shop_id IS NULL
        ");

        $result = $stmt->execute([
            'id' => $barberId,
            'shop_id' => $shopId,
        ]);

        if ($result && $stmt->rowCount() > 0) {
            $this->invalidateDiscoveryCache();
        }

        return $result;
    }

    public function findById(int $id): ?Barber
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM barbers
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id
        ]);

        $data = $stmt->fetch();

        return $data ? new Barber($data) : null;
    }

    public function findByUserId(int $userId): ?Barber
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM barbers
            WHERE user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            'user_id' => $userId
        ]);

        $data = $stmt->fetch();

        return $data ? new Barber($data) : null;
    }

    public function findByShopId(int $shopId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM barbers
            WHERE shop_id = :shop_id
            ORDER BY created_at DESC
        ");

        $stmt->execute([
            'shop_id' => $shopId
        ]);

        $barbers = [];

        foreach ($stmt->fetchAll() as $data) {
            $barbers[] = new Barber($data);
        }

        return $barbers;
    }

    /**
     * Find all approved and active barbers that customers can discover.
     *
     * Independent barbers:
     * - shop_id IS NULL
     * - must be approved and active
     *
     * Shop-assigned barbers:
     * - must belong to an approved and active shop
     * - must also be approved and active themselves
     *
     * Optional search can match barber speciality.
     */
    public function findDiscoverableBarbers(
        ?string $search = null
    ): array {
        $sql = "
        SELECT b.*
        FROM barbers b
        LEFT JOIN shops s
            ON s.id = b.shop_id
        WHERE b.approval_status = 'approved'
          AND b.status = 'active'
          AND (
                b.shop_id IS NULL
                OR (
                    s.approval_status = 'approved'
                    AND s.status = 'active'
                )
          )
    ";

        $params = [];

        if ($search !== null && trim($search) !== '') {
            $sql .= "
            AND b.speciality LIKE :search
        ";

            $params['search'] = '%' . trim($search) . '%';
        }

        $sql .= "
        ORDER BY b.created_at DESC
    ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute($params);

        $barbers = [];

        foreach ($stmt->fetchAll() as $data) {
            $barbers[] = new Barber($data);
        }

        return $barbers;
    }


     //Update barber approval status.
    public function updateApprovalStatus(
        int $barberId,
        string $approvalStatus,
        ?int $approvedBy = null
    ): bool {
        if ($approvalStatus === 'approved') {
            $stmt = $this->db->prepare("
                UPDATE barbers
                SET
                    approval_status = 'approved',
                    status = 'active',
                    approved_at = CURRENT_TIMESTAMP,
                    approved_by = :approved_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");

            $result = $stmt->execute([
                'id' => $barberId,
                'approved_by' => $approvedBy
            ]);

            if ($result && $stmt->rowCount() > 0) {
                $this->invalidateDiscoveryCache();
            }

            return $result;
        }

        if ($approvalStatus === 'rejected') {
            $stmt = $this->db->prepare("
                UPDATE barbers
                SET
                    approval_status = 'rejected',
                    status = 'inactive',
                    approved_at = NULL,
                    approved_by = :approved_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");

            $result = $stmt->execute([
                'id' => $barberId,
                'approved_by' => $approvedBy
            ]);

            if ($result && $stmt->rowCount() > 0) {
                $this->invalidateDiscoveryCache();
            }

            return $result;
        }

        throw new \InvalidArgumentException(
            'Invalid barber approval status.'
        );
    }
}