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
        string $billingPeriod,
        ?string $startDate = null,
        ?string $endDate = null
    ): ?Subscription {

        if (!in_array($billingPeriod, ['monthly', 'yearly'], true)) {
            throw new \InvalidArgumentException(
                'Invalid subscription billing period.'
            );
        }

        $stmt = $this->db->prepare("
        INSERT INTO subscriptions (
            user_id,
            plan_id,
            plan_version_id,
            billing_period,
            start_date,
            end_date,
            status
        )
        VALUES (
            :user_id,
            :plan_id,
            :plan_version_id,
            :billing_period,
            :start_date,
            :end_date,
            'pending'
        )
    ");

        $result = $stmt->execute([
            'user_id' => $userId,
            'plan_id' => $planId,
            'plan_version_id' => $planVersionId,
            'billing_period' => $billingPeriod,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        if (!$result) {
            return null;
        }

        $id = (int) $this->db->lastInsertId();

        return $this->findById($id);
    }



    public function createEntitlement(
        int $userId,
        int $planId,
        int $planVersionId,
        string $billingPeriod,
        string $scope,
        string $beneficiaryType,
        int $beneficiaryId
    ): ?Subscription {

        if (!in_array($billingPeriod, ['monthly', 'yearly'], true)) {
            throw new \InvalidArgumentException(
                'Invalid subscription billing period.'
            );
        }

        $validCombinations = [
            'HOME' => 'BARBER',
            'SHOP' => 'SHOP',
            'VENDOR' => 'VENDOR',
        ];

        if (
            !isset($validCombinations[$scope]) ||
            $validCombinations[$scope] !== $beneficiaryType ||
            $userId <= 0 ||
            $planId <= 0 ||
            $planVersionId <= 0 ||
            $beneficiaryId <= 0
        ) {
            throw new \InvalidArgumentException(
                'Invalid subscription entitlement details.'
            );
        }

        // Verify that the plan matches the requested entitlement.
        $stmt = $this->db->prepare("
        SELECT role, scope
        FROM plans
        WHERE id = :plan_id
        LIMIT 1
    ");

        $stmt->execute([
            'plan_id' => $planId,
        ]);

        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$plan ||
            $plan['role'] !== (
            $beneficiaryType === 'SHOP'
                ? 'SHOP_OWNER'
                : $beneficiaryType
            ) ||
            $plan['scope'] !== $scope
        ) {
            throw new \InvalidArgumentException(
                'The selected plan does not match the subscription entitlement.'
            );
        }

        // Verify that the pricing version belongs to this plan
        // and has been approved.
        $stmt = $this->db->prepare("
        SELECT 1
        FROM plan_versions
        WHERE id = :version_id
          AND plan_id = :plan_id
          AND status = 'approved'
        LIMIT 1
    ");

        $stmt->execute([
            'version_id' => $planVersionId,
            'plan_id' => $planId,
        ]);

        if ($stmt->fetchColumn() === false) {
            throw new \InvalidArgumentException(
                'The selected plan version is invalid or not approved.'
            );
        }

        $stmt = $this->db->prepare("
        INSERT INTO subscriptions (
            user_id,
            plan_id,
            scope,
            beneficiary_type,
            beneficiary_id,
            entitlement_status,
            plan_version_id,
            billing_period,
            status
        )
        VALUES (
            :user_id,
            :plan_id,
            :scope,
            :beneficiary_type,
            :beneficiary_id,
            'VALID',
            :plan_version_id,
            :billing_period,
            'pending'
        )
    ");

        $result = $stmt->execute([
            'user_id' => $userId,
            'plan_id' => $planId,
            'scope' => $scope,
            'beneficiary_type' => $beneficiaryType,
            'beneficiary_id' => $beneficiaryId,
            'plan_version_id' => $planVersionId,
            'billing_period' => $billingPeriod,
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

    public function findByIdForUpdate(
        int $subscriptionId
    ): ?Subscription {

        $stmt = $this->db->prepare("
        SELECT *
        FROM subscriptions
        WHERE id = :id
        LIMIT 1
        FOR UPDATE
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


    public function findPendingByBeneficiary(
        string $beneficiaryType,
        int $beneficiaryId,
        string $scope
    ): ?Subscription {
        $stmt = $this->db->prepare("
        SELECT *
        FROM subscriptions
        WHERE beneficiary_type = :beneficiary_type
          AND beneficiary_id = :beneficiary_id
          AND scope = :scope
          AND entitlement_status = 'VALID'
          AND status = 'pending'
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");

        $stmt->execute([
            'beneficiary_type' => $beneficiaryType,
            'beneficiary_id' => $beneficiaryId,
            'scope' => $scope,
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? new Subscription($data) : null;
    }



    public function findActiveByBeneficiary(
        string $beneficiaryType,
        int $beneficiaryId,
        string $scope
    ): ?Subscription {
        $stmt = $this->db->prepare("
        SELECT *
        FROM subscriptions
        WHERE beneficiary_type = :beneficiary_type
          AND beneficiary_id = :beneficiary_id
          AND scope = :scope
          AND entitlement_status = 'VALID'
          AND status = 'active'
          AND start_date <= CURRENT_TIMESTAMP
          AND end_date > CURRENT_TIMESTAMP
        ORDER BY end_date DESC
        LIMIT 1
    ");

        $stmt->execute([
            'beneficiary_type' => $beneficiaryType,
            'beneficiary_id' => $beneficiaryId,
            'scope' => $scope,
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? new Subscription($data) : null;
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
        $allowedStatuses = [
            'pending',
            'active',
            'expired',
            'cancelled',
            'suspended',
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            throw new \InvalidArgumentException(
                'Invalid subscription status.'
            );
        }

        $stmt = $this->db->prepare("
        UPDATE subscriptions
        SET status = :status
        WHERE id = :id
    ");

        $stmt->execute([
            'status' => $status,
            'id' => $subscriptionId,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function activate(
        int $subscriptionId,
        string $startDate,
        string $endDate
    ): bool {
        $stmt = $this->db->prepare("
        UPDATE subscriptions
        SET
            status = 'active',
            start_date = :start_date,
            end_date = :end_date
        WHERE id = :id
          AND status = 'pending'
          AND entitlement_status = 'VALID'
    ");

        $stmt->execute([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'id' => $subscriptionId,
        ]);

        return $stmt->rowCount() === 1;
    }


    public function hasActiveSubscription(int $userId): bool
    {
        $stmt = $this->db->prepare("
        SELECT 1
        FROM subscriptions
        WHERE user_id = :user_id
          AND status = 'active'
          AND end_date > CURRENT_TIMESTAMP
        LIMIT 1
    ");

        $stmt->execute([
            'user_id' => $userId
        ]);

        return $stmt->fetchColumn() !== false;
    }


    /**
     * Check whether a beneficiary has a currently valid entitlement
     * for a specific service scope.
     */
    public function hasActiveEntitlement(
        string $beneficiaryType,
        int $beneficiaryId,
        string $scope
    ): bool {
        $allowedTypes = ['BARBER', 'SHOP', 'VENDOR'];
        $allowedScopes = ['HOME', 'SHOP', 'VENDOR'];

        if (
            !in_array($beneficiaryType, $allowedTypes, true) ||
            !in_array($scope, $allowedScopes, true) ||
            $beneficiaryId <= 0
        ) {
            throw new \InvalidArgumentException(
                'Invalid subscription beneficiary or scope.'
            );
        }

        $stmt = $this->db->prepare("
        SELECT 1
        FROM subscriptions
        WHERE beneficiary_type = :beneficiary_type
          AND beneficiary_id = :beneficiary_id
          AND scope = :scope
          AND entitlement_status = 'VALID'
          AND status = 'active'
          AND start_date <= CURRENT_TIMESTAMP
          AND end_date > CURRENT_TIMESTAMP
        LIMIT 1
    ");

        $stmt->execute([
            'beneficiary_type' => $beneficiaryType,
            'beneficiary_id' => $beneficiaryId,
            'scope' => $scope,
        ]);

        return $stmt->fetchColumn() !== false;
    }



    /**
     * Return user IDs that currently have active subscriptions.
     *
     * A subscription must have active status and a future end date.
     *
     * @param int[] $userIds
     * @return int[]
     */
    public function findUserIdsWithActiveSubscriptions(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(
            array_map('intval', $userIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(
            ', ',
            array_fill(0, count($userIds), '?')
        );

        $stmt = $this->db->prepare("
        SELECT DISTINCT user_id
        FROM subscriptions
        WHERE user_id IN ($placeholders)
          AND status = 'active'
          AND end_date > CURRENT_TIMESTAMP
    ");

        $stmt->execute($userIds);

        return array_map(
            'intval',
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );
    }



}