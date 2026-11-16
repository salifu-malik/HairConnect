<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use App\Helpers\RedisManager;
use PDO;

class BarberShopMembershipRepository
{
    private PDO $db;
    private RedisManager $redis;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection('booking_db');
        $this->redis = new RedisManager();
    }

    /**
     * Invalidate public barber discovery cache.
     */
    private function invalidateDiscoveryCache(): void
    {
        $this->redis->delete('barbers:discoverable');
    }


/**
 * Create a pending shop membership application.
 *
 * Updates the membership history and the barber's
 * operational shop assignment in one transaction.
 */

public function createPendingMembership(
    int $barberId,
    int $shopId
): bool {
    if ($barberId <= 0 || $shopId <= 0) {
        throw new \InvalidArgumentException(
            'Invalid barber or shop ID.'
        );
    }

    try {
        $this->db->beginTransaction();

        /*
         * STEP 1: Lock the barber record.
         */
        $barberStmt = $this->db->prepare("
            SELECT id, shop_id
            FROM barbers
            WHERE id = :barber_id
            LIMIT 1
            FOR UPDATE
        ");

        $barberStmt->execute([
            'barber_id' => $barberId,
        ]);

        $barber = $barberStmt->fetch(PDO::FETCH_ASSOC);

        if (!$barber) {
            throw new \RuntimeException(
                'Barber not found.'
            );
        }

        if ($barber['shop_id'] !== null) {
            throw new \RuntimeException(
                'Barber already has a shop assignment or pending application.'
            );
        }

        /*
         * STEP 2: Verify that the shop exists.
         */
        $shopStmt = $this->db->prepare("
            SELECT id
            FROM shops
            WHERE id = :shop_id
            LIMIT 1
            FOR UPDATE
        ");

        $shopStmt->execute([
            'shop_id' => $shopId,
        ]);

        if (!$shopStmt->fetch(PDO::FETCH_ASSOC)) {
            throw new \RuntimeException(
                'Shop not found.'
            );
        }

        /*
         * STEP 3: Create pending membership history.
         */
        $membershipStmt = $this->db->prepare("
            INSERT INTO barber_shop_memberships (
                barber_id,
                shop_id,
                status
            )
            VALUES (
                :barber_id,
                :shop_id,
                'pending'
            )
        ");

        $membershipStmt->execute([
            'barber_id' => $barberId,
            'shop_id' => $shopId,
        ]);

        /*
         * STEP 4: Set the operational shop assignment.
         *
         * Preserve platform approval and operational status.
         */
        $updateStmt = $this->db->prepare("
            UPDATE barbers
            SET
                shop_id = :shop_id,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :barber_id
              AND shop_id IS NULL
        ");

        $updateStmt->execute([
            'shop_id' => $shopId,
            'barber_id' => $barberId,
        ]);

        if ($updateStmt->rowCount() !== 1) {
            throw new \RuntimeException(
                'Failed to update barber shop assignment.'
            );
        }

        $this->db->commit();

        $this->invalidateDiscoveryCache();

        return true;

    } catch (\Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        throw $e;
    }
}



/**
 * Approve or reject a pending shop membership.
 *
 * Updates membership history and barber profile atomically.
 */
    public function reviewMembership(
        int $barberId,
        int $shopId,
        int $reviewedBy,
        string $decision
    ): bool {
        if (
            $barberId <= 0 ||
            $shopId <= 0 ||
            $reviewedBy <= 0
        ) {
            throw new \InvalidArgumentException(
                'Invalid membership identifiers.'
            );
        }

        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException(
                'Invalid membership decision.'
            );
        }

        // Map application decisions to database membership statuses.
        $membershipStatus = $decision === 'approved'
            ? 'active'
            : 'rejected';

        try {
            $this->db->beginTransaction();

            /*
             * STEP 1: Lock and validate the barber.
             *
             * Do not require approval_status = 'pending'.
             * Platform approval and shop membership are separate processes.
             */
            $barberStmt = $this->db->prepare("
            SELECT id, shop_id, approval_status, status
            FROM barbers
            WHERE id = :barber_id
            LIMIT 1
            FOR UPDATE
        ");

            $barberStmt->execute([
                'barber_id' => $barberId,
            ]);

            $barber = $barberStmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$barber ||
                (int) $barber['shop_id'] !== $shopId
            ) {
                throw new \RuntimeException(
                    'No matching barber application exists.'
                );
            }

            /*
             * STEP 2: Lock the corresponding pending membership.
             */
            $membershipStmt = $this->db->prepare("
            SELECT id
            FROM barber_shop_memberships
            WHERE barber_id = :barber_id
              AND shop_id = :shop_id
              AND status = 'pending'
            LIMIT 1
            FOR UPDATE
        ");

            $membershipStmt->execute([
                'barber_id' => $barberId,
                'shop_id' => $shopId,
            ]);

            $membership = $membershipStmt->fetch(PDO::FETCH_ASSOC);

            if (!$membership) {
                throw new \RuntimeException(
                    'Pending membership record not found.'
                );
            }

            /*
             * STEP 3: Update membership history.
             *
             * The database accepts:
             * - active
             * - rejected
             *
             * It does not accept 'approved' as a membership status.
             */
            $membershipUpdate = $this->db->prepare("
            UPDATE barber_shop_memberships
            SET
                status = :status,
                reviewed_by = :reviewed_by,
                approved_at = CASE
                    WHEN :approved_at_status = 'active'
                    THEN CURRENT_TIMESTAMP
                    ELSE NULL
                END,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :membership_id
              AND status = 'pending'
        ");

            $membershipUpdate->execute([
                'status' => $membershipStatus,
                'reviewed_by' => $reviewedBy,
                'approved_at_status' => $membershipStatus,
                'membership_id' => $membership['id'],
            ]);

            if ($membershipUpdate->rowCount() !== 1) {
                throw new \RuntimeException(
                    'Failed to update membership status.'
                );
            }

            /*
             * STEP 4: Synchronize the barber's shop assignment.
             *
             * Approval:
             * - Retain shop_id.
             *
             * Rejection:
             * - Clear shop_id.
             *
             * IMPORTANT:
             * Do not modify:
             * - approval_status
             * - approved_at
             * - approved_by
             * - status
             *
             * These fields belong to the platform approval
             * and operational eligibility processes.
             */
            if ($decision === 'rejected') {
                $barberUpdate = $this->db->prepare("
                UPDATE barbers
                SET
                    shop_id = NULL,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :barber_id
                  AND shop_id = :shop_id
            ");

                $barberUpdate->execute([
                    'barber_id' => $barberId,
                    'shop_id' => $shopId,
                ]);

                if ($barberUpdate->rowCount() !== 1) {
                    throw new \RuntimeException(
                        'Failed to clear barber shop assignment.'
                    );
                }
            }

            /*
             * STEP 5: Commit the transaction.
             */
            $this->db->commit();

            $this->invalidateDiscoveryCache();

            return true;

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }



/**
 * Terminate an active shop membership.
 *
 * Does not affect the barber's personal subscription.
 */
    public function terminateMembership(
        int $barberId,
        int $shopId
    ): bool {
        if ($barberId <= 0 || $shopId <= 0) {
            throw new \InvalidArgumentException(
                'Invalid barber or shop ID.'
            );
        }

        try {
            $this->db->beginTransaction();

            /*
             * STEP 1: Lock the barber record.
             */
            $barberStmt = $this->db->prepare("
            SELECT id, shop_id
            FROM barbers
            WHERE id = :barber_id
            LIMIT 1
            FOR UPDATE
        ");

            $barberStmt->execute([
                'barber_id' => $barberId,
            ]);

            $barber = $barberStmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$barber ||
                (int) $barber['shop_id'] !== $shopId
            ) {
                throw new \RuntimeException(
                    'Barber does not belong to this shop.'
                );
            }

            /*
             * STEP 2: Terminate the active membership.
             */
            $membershipStmt = $this->db->prepare("
            UPDATE barber_shop_memberships
            SET
                status = 'terminated',
                terminated_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE barber_id = :barber_id
              AND shop_id = :shop_id
              AND status = 'active'
        ");

            $membershipStmt->execute([
                'barber_id' => $barberId,
                'shop_id' => $shopId,
            ]);

            if ($membershipStmt->rowCount() !== 1) {
                throw new \RuntimeException(
                    'No active membership exists for this barber.'
                );
            }

            /*
             * STEP 3: Clear the shop assignment.
             *
             * Preserve:
             * - Platform approval status.
             * - Operational status.
             * - Platform approval timestamp.
             * - Platform approving administrator.
             *
             * The barber may continue providing HOME services
             * if their personal HOME entitlement is valid.
             */
            $barberUpdate = $this->db->prepare("
            UPDATE barbers
            SET
                shop_id = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :barber_id
              AND shop_id = :shop_id
        ");

            $barberUpdate->execute([
                'barber_id' => $barberId,
                'shop_id' => $shopId,
            ]);

            if ($barberUpdate->rowCount() !== 1) {
                throw new \RuntimeException(
                    'Failed to clear barber shop assignment.'
                );
            }

            /*
             * STEP 4: Commit.
             */
            $this->db->commit();

            $this->invalidateDiscoveryCache();

            return true;

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }



/**
 * Retrieve a barber's active shop membership.
 */
public function findActiveByBarberId(int $barberId): ?array
{
    if ($barberId <= 0) {
        throw new \InvalidArgumentException('Invalid barber ID.');
    }

    $stmt = $this->db->prepare("
        SELECT
            m.id,
            m.barber_id,
            m.shop_id,
            m.status,
            m.applied_at,
            m.approved_at,
            m.reviewed_by,
            s.name AS shop_name,
            s.owner_id AS shop_owner_id
        FROM barber_shop_memberships m
        INNER JOIN shops s ON s.id = m.shop_id
        WHERE m.barber_id = :barber_id
          AND m.status = 'active'
        LIMIT 1
    ");

    $stmt->execute([
        'barber_id' => $barberId,
    ]);

    $membership = $stmt->fetch(PDO::FETCH_ASSOC);

    return $membership ?: null;
}



/**
 * Retrieve all historical shop memberships for a barber.
 */
public function findMembershipHistory(int $barberId): array
{
    if ($barberId <= 0) {
        throw new \InvalidArgumentException('Invalid barber ID.');
    }

    $stmt = $this->db->prepare("
        SELECT
            m.id,
            m.barber_id,
            m.shop_id,
            m.status,
            m.applied_at,
            m.approved_at,
            m.terminated_at,
            m.reviewed_by,
            s.name AS shop_name
        FROM barber_shop_memberships m
        INNER JOIN shops s ON s.id = m.shop_id
        WHERE m.barber_id = :barber_id
        ORDER BY m.created_at DESC, m.id DESC
    ");

    $stmt->execute([
        'barber_id' => $barberId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}
