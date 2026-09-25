<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use App\Models\PlanVersion;
use PDO;

class PlanVersionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection(
            'subscription_db'
        );
    }

    public function create(
        int $planId,
        float $monthlyPrice,
        float $yearlyDiscountPercent,
        int $proposedBy
    ): ?PlanVersion {
        $stmt = $this->db->prepare("
            INSERT INTO plan_versions (
                plan_id,
                monthly_price,
                yearly_discount_percent,
                status,
                proposed_by
            )
            VALUES (
                :plan_id,
                :monthly_price,
                :yearly_discount_percent,
                'pending',
                :proposed_by
            )
        ");

        $result = $stmt->execute([
            'plan_id' => $planId,
            'monthly_price' => $monthlyPrice,
            'yearly_discount_percent' =>
                $yearlyDiscountPercent,
            'proposed_by' => $proposedBy
        ]);

        if (!$result) {
            return null;
        }

        $id = (int) $this->db->lastInsertId();

        return $this->findById($id);
    }

    public function findById(
        int $planVersionId
    ): ?PlanVersion {
        $stmt = $this->db->prepare("
            SELECT *
            FROM plan_versions
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $planVersionId
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return new PlanVersion($data);
    }

    public function findPending(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM plan_versions
            WHERE status = 'pending'
            ORDER BY proposed_at ASC
        ");

        $versions = [];

        foreach (
            $stmt->fetchAll(PDO::FETCH_ASSOC)
            as $data
        ) {
            $versions[] = new PlanVersion($data);
        }

        return $versions;
    }

    public function findApprovedByPlanId(
        int $planId
    ): ?PlanVersion {
        $stmt = $this->db->prepare("
            SELECT *
            FROM plan_versions
            WHERE plan_id = :plan_id
              AND status = 'approved'
            ORDER BY approved_at DESC, id DESC
            LIMIT 1
        ");

        $stmt->execute([
            'plan_id' => $planId
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return new PlanVersion($data);
    }

    public function updateApprovalStatus(
        int $planVersionId,
        string $status,
        int $adminId,
        ?string $rejectionReason = null
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE plan_versions
            SET
                status = :status,
                approved_by = :approved_by,
                approved_at = CASE
                    WHEN :status = 'approved'
                    THEN CURRENT_TIMESTAMP
                    ELSE NULL
                END,
                rejection_reason = :rejection_reason
            WHERE id = :id
              AND status = 'pending'
        ");

        return $stmt->execute([
            'status' => $status,
            'approved_by' => $adminId,
            'rejection_reason' => $rejectionReason,
            'id' => $planVersionId
        ]);
    }

    public function findAll(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM plan_versions
            ORDER BY id DESC
        ");

        $versions = [];

        foreach (
            $stmt->fetchAll(PDO::FETCH_ASSOC)
            as $data
        ) {
            $versions[] = new PlanVersion($data);
        }

        return $versions;
    }
}