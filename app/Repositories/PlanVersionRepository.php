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

    //To prevent runtime error when Finance Manager submits pricing proposal
    public function findPendingByPlanId(
        int $planId
    ): ?PlanVersion {
        $stmt = $this->db->prepare("
        SELECT *
        FROM plan_versions
        WHERE plan_id = :plan_id
          AND status = 'pending'
        ORDER BY proposed_at DESC, id DESC
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

    //Active pricing lookup
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
        int $approvedBy,
        ?string $rejectionReason = null
    ): bool {
        try {
            $this->db->beginTransaction();

            // If approving this pricing version,
            // deactivate any previously approved version
            // for the same plan.
            if ($status === 'approved') {
                $stmt = $this->db->prepare("
                SELECT plan_id
                FROM plan_versions
                WHERE id = :id
                LIMIT 1
            ");

                $stmt->execute([
                    'id' => $planVersionId
                ]);

                $planId = $stmt->fetchColumn();

                if (!$planId) {
                    $this->db->rollBack();
                    return false;
                }

                $stmt = $this->db->prepare("
                UPDATE plan_versions
                SET status = 'inactive'
                WHERE plan_id = :plan_id
                  AND status = 'approved'
                  AND id != :id
            ");

                $stmt->execute([
                    'plan_id' => $planId,
                    'id' => $planVersionId
                ]);
            }

            $stmt = $this->db->prepare("
            UPDATE plan_versions
            SET
                status = :status,
                approved_by = :approved_by,
                approved_at = CURRENT_TIMESTAMP,
                rejection_reason = :rejection_reason
            WHERE id = :id
              AND status = 'pending'
        ");

            $result = $stmt->execute([
                'status' => $status,
                'approved_by' => $approvedBy,
                'rejection_reason' => $rejectionReason,
                'id' => $planVersionId
            ]);

            if (!$result || $stmt->rowCount() === 0) {
                $this->db->rollBack();
                return false;
            }

            $this->db->commit();

            return true;

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
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