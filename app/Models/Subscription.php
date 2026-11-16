<?php

namespace App\Models;

class Subscription
{
    public int $id;
    public int $userId;
    public int $planId;
    public ?int $planVersionId;
    public ?string $scope;
    public ?string $beneficiaryType;
    public ?int $beneficiaryId;
    public string $entitlementStatus;
    public ?string $startDate;
    public ?string $endDate;
    public ?string $billingPeriod;
    public string $status;
    public string $createdAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->userId = (int) $data['user_id'];
        $this->planId = (int) $data['plan_id'];

        $this->planVersionId =
            isset($data['plan_version_id'])
                ? (int) $data['plan_version_id']
                : null;


        $this->scope = $data['scope'] ?? null;
        $this->beneficiaryType = $data['beneficiary_type'] ?? null;

        $this->beneficiaryId = isset($data['beneficiary_id'])
            ? (int) $data['beneficiary_id']
            : null;

        $this->entitlementStatus =
            $data['entitlement_status'] ?? 'LEGACY';

        $this->startDate = $data['start_date'] ?? null;
        $this->endDate = $data['end_date'] ?? null;
        $this->billingPeriod = $data['billing_period'] ?? null;
        $this->status = $data['status'];
        $this->createdAt = $data['created_at'];
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'plan_id' => $this->planId,
            'plan_version_id' => $this->planVersionId,
            'scope' => $this->scope,
            'beneficiary_type' => $this->beneficiaryType,
            'beneficiary_id' => $this->beneficiaryId,
            'entitlement_status' => $this->entitlementStatus,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'billing_period' => $this->billingPeriod,
            'status' => $this->status,
            'created_at' => $this->createdAt,
        ];
    }
}
