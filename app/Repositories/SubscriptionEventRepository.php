<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use PDO;

class SubscriptionEventRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection(
            'subscription_db'
        );
    }

    public function create(
        ?int $subscriptionId,
        ?int $planVersionId,
        ?int $userId,
        string $eventType,
        string $description,
        int $performedBy
    ): bool {
        $stmt = $this->db->prepare("
            INSERT INTO subscription_events (
                subscription_id,
                plan_version_id,
                user_id,
                event_type,
                description,
                performed_by
            )
            VALUES (
                :subscription_id,
                :plan_version_id,
                :user_id,
                :event_type,
                :description,
                :performed_by
            )
        ");

        return $stmt->execute([
            'subscription_id' => $subscriptionId,
            'plan_version_id' => $planVersionId,
            'user_id' => $userId,
            'event_type' => $eventType,
            'description' => $description,
            'performed_by' => $performedBy
        ]);
    }

    public function findBySubscriptionId(
        int $subscriptionId
    ): array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM subscription_events
            WHERE subscription_id = :subscription_id
            ORDER BY created_at DESC, id DESC
        ");

        $stmt->execute([
            'subscription_id' => $subscriptionId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByPlanVersionId(
        int $planVersionId
    ): array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM subscription_events
            WHERE plan_version_id = :plan_version_id
            ORDER BY created_at DESC, id DESC
        ");

        $stmt->execute([
            'plan_version_id' => $planVersionId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}