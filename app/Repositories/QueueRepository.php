<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use PDO;

class QueueRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection('booking_db');
    }

    /**
     * Find a customer's active queue entry at a specific shop.
     *
     * Active means waiting or currently in progress.
     */
    public function findActiveByCustomerAndShop(
        int $customerId,
        int $shopId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                shop_id,
                customer_id,
                queue_number,
                status,
                created_at,
                updated_at
            FROM queues
            WHERE customer_id = :customer_id
              AND shop_id = :shop_id
              AND status IN ('waiting', 'in_progress')
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            'customer_id' => $customerId,
            'shop_id' => $shopId,
        ]);

        $queue = $stmt->fetch();

        return $queue ?: null;
    }

    /**
     * Lock the shop row.
     *
     * This is used inside a transaction to serialize queue-number
     * generation for the same shop.
     */
    public function lockShop(int $shopId): void
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM shops
            WHERE id = :shop_id
            FOR UPDATE
        ");

        $stmt->execute([
            'shop_id' => $shopId,
        ]);

        $stmt->fetch();
    }

    /**
     * Get the next queue number for a shop.
     *
     * This method must be called after lockShop() inside
     * the same transaction.
     */
    public function getNextQueueNumber(int $shopId): int
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(queue_number), 0) + 1 AS next_queue_number
            FROM queues
            WHERE shop_id = :shop_id
        ");

        $stmt->execute([
            'shop_id' => $shopId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Create a queue entry.
     */
    public function create(
        int $shopId,
        int $customerId,
        int $queueNumber
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO queues (
                shop_id,
                customer_id,
                queue_number,
                status
            )
            VALUES (
                :shop_id,
                :customer_id,
                :queue_number,
                'waiting'
            )
        ");

        $stmt->execute([
            'shop_id' => $shopId,
            'customer_id' => $customerId,
            'queue_number' => $queueNumber,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Find the current position of a queue entry.
     *
     * Only waiting and in-progress customers are counted.
     *
     * Example:
     * #1 completed
     * #2 waiting
     * #3 waiting
     *
     * Customer #3 has position 2.
     */
    public function getCurrentPosition(
        int $shopId,
        int $queueNumber
    ): int {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) + 1 AS position
            FROM queues
            WHERE shop_id = :shop_id
              AND queue_number < :queue_number
              AND status IN ('waiting', 'in_progress')
        ");

        $stmt->execute([
            'shop_id' => $shopId,
            'queue_number' => $queueNumber,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Get a queue entry by ID.
     */
    public function findById(int $queueId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                shop_id,
                customer_id,
                queue_number,
                status,
                created_at,
                updated_at
            FROM queues
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $queueId,
        ]);

        $queue = $stmt->fetch();

        return $queue ?: null;
    }

    /**
     * Begin database transaction.
     */
    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    /**
     * Get all active queue entries for a shop.
     *
     * Active means waiting or currently in progress.
     */
    public function findActiveByShop(int $shopId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            id,
            shop_id,
            customer_id,
            queue_number,
            status,
            created_at,
            updated_at
        FROM queues
        WHERE shop_id = :shop_id
          AND status IN ('waiting', 'in_progress')
        ORDER BY queue_number ASC
    ");

        $stmt->execute([
            'shop_id' => $shopId,
        ]);

        return $stmt->fetchAll();
    }


    /**
     * Move a waiting queue entry to in_progress.
     */
    public function start(int $queueId): bool
    {
        $stmt = $this->db->prepare("
        UPDATE queues
        SET
            status = 'in_progress',
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND status = 'waiting'
    ");

        $stmt->execute([
            'id' => $queueId,
        ]);

        return $stmt->rowCount() > 0;
    }


    /**
     * Move an in-progress queue entry to completed.
     */
    public function complete(int $queueId): bool
    {
        $stmt = $this->db->prepare("
        UPDATE queues
        SET
            status = 'completed',
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND status = 'in_progress'
    ");

        $stmt->execute([
            'id' => $queueId,
        ]);

        return $stmt->rowCount() > 0;
    }


    /**
     * Cancel a waiting queue entry.
     */
    public function cancel(int $queueId): bool
    {
        $stmt = $this->db->prepare("
        UPDATE queues
        SET
            status = 'cancelled',
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND status = 'waiting'
    ");

        $stmt->execute([
            'id' => $queueId,
        ]);

        return $stmt->rowCount() > 0;
    }


    /**
     * Commit database transaction.
     */
    public function commit(): void
    {
        $this->db->commit();
    }

    /**
     * Roll back database transaction.
     */
    public function rollback(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }
}