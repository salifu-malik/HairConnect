<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use App\Models\Subscription;
use PDO;

class SubscriptionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection(
            'subscription_db'
        );
    }

    public function create(
        int $userId,
        int $planId,
        int $planVersionId,
        string $startDate,
        string $endDate
    ): ?Subscription {
        $stmt = $this->db->prepare("
            INSERT INTO subscriptions (
                user_id,
                plan_id,
                plan_version_id,
                start_date,
                end_date,
                status
            )
            VALUES (
                :user_id,
                :plan_id,
                :plan_version_id,
                :start_date,
                :end_date,
                'pending'
            )
        ");

        $result = $stmt->execute([
            'user_id' => $userId,
            'plan_id' => $planId,
            'plan_version_id' => $planVersionId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        if (!$result) {
            return null;
        }

        $id = (int) $this->db->lastInsertId();

        return $this->findById($id);
    }

    public function findById(
        int $subscriptionId
    ): ?Subscription {
        $stmt = $this->db->prepare("
            SELECT *
            FROM subscriptions
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $subscriptionId
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return new Subscription($data);
    }

    public function findActiveByUserId(
        int $userId
    ): ?Subscription {
        $stmt = $this->db->prepare("
            SELECT *
            FROM subscriptions
            WHERE user_id = :user_id
              AND status = 'active'
              AND end_date > CURRENT_TIMESTAMP
            ORDER BY end_date DESC
            LIMIT 1
        ");

        $stmt->execute([
            'user_id' => $userId
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return new Subscription($data);
    }

    public function findPendingByUserId(int $userId): ?Subscription
    {
        $stmt = $this->db->prepare("
        SELECT *
        FROM subscriptions
        WHERE user_id = :user_id
          AND status = 'pending'
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");

        $stmt->execute([
            'user_id' => $userId
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return new Subscription($data);
    }

    public function findByUserId(
        int $userId
    ): array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM subscriptions
            WHERE user_id = :user_id
            ORDER BY created_at DESC
        ");

        $stmt->execute([
            'user_id' => $userId
        ]);

        $subscriptions = [];

        foreach (
            $stmt->fetchAll(PDO::FETCH_ASSOC)
            as $data
        ) {
            $subscriptions[] = new Subscription($data);
        }

        return $subscriptions;
    }

    public function updateStatus(
        int $subscriptionId,
        string $status
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE subscriptions
            SET status = :status
            WHERE id = :id
        ");

        return $stmt->execute([
            'status' => $status,
            'id' => $subscriptionId
        ]);
    }
}