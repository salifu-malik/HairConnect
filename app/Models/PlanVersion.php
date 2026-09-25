<?php

namespace App\Models;

class PlanVersion
{
    public int $id;
    public int $planId;
    public float $monthlyPrice;
    public float $yearlyDiscountPercent;
    public string $status;
    public int $proposedBy;
    public ?int $approvedBy;
    public string $proposedAt;
    public ?string $approvedAt;
    public ?string $rejectionReason;
    public string $createdAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->planId = (int) $data['plan_id'];
        $this->monthlyPrice = (float) $data['monthly_price'];
        $this->yearlyDiscountPercent =
            (float) $data['yearly_discount_percent'];

        $this->status = $data['status'];
        $this->proposedBy = (int) $data['proposed_by'];

        $this->approvedBy = isset($data['approved_by'])
            ? (int) $data['approved_by']
            : null;

        $this->proposedAt = $data['proposed_at'];

        $this->approvedAt = $data['approved_at']
            ?? null;

        $this->rejectionReason =
            $data['rejection_reason']
            ?? null;

        $this->createdAt = $data['created_at'];
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->planId,
            'monthly_price' => $this->monthlyPrice,
            'yearly_discount_percent' =>
                $this->yearlyDiscountPercent,
            'status' => $this->status,
            'proposed_by' => $this->proposedBy,
            'approved_by' => $this->approvedBy,
            'proposed_at' => $this->proposedAt,
            'approved_at' => $this->approvedAt,
            'rejection_reason' => $this->rejectionReason,
            'created_at' => $this->createdAt,
        ];
    }
}