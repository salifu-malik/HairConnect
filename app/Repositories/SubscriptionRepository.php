<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use PDO;

class SubscriptionRepository {
    private PDO $db;

    public function __construct() {
        $this->db = DatabaseManager::getConnection('subscription_db');
    }

    public function createSubscription(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO subscriptions (user_id, plan_id, end_date, status)
            VALUES (:user_id, :plan_id, :end_date, :status)
        ");
        $stmt->execute([
            'user_id' => $data['user_id'],
            'plan_id' => $data['plan_id'],
            'end_date' => $data['end_date'],
            'status' => $data['status'] ?? 'active',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getPlanById(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM plans WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
