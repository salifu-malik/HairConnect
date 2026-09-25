<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use App\Models\Plan;
use PDO;

class PlanRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection(
            'subscription_db'
        );
    }

    public function findById(int $planId): ?Plan
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM plans
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $planId
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return new Plan($data);
    }

    public function findByRole(string $role): ?Plan
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM plans
            WHERE role = :role
            LIMIT 1
        ");

        $stmt->execute([
            'role' => $role
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return new Plan($data);
    }

    public function findAll(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM plans
            ORDER BY id ASC
        ");

        $plans = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $data) {
            $plans[] = new Plan($data);
        }

        return $plans;
    }
}