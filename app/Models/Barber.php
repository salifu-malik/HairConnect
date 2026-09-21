<?php

namespace App\Models;

class Barber
{
    public int $id;
    public int $userId;
    public ?int $shopId;
    public int $experience;
    public ?string $speciality;

    public string $approvalStatus;
    public ?string $approvedAt;
    public ?int $approvedBy;

    public string $status;

    public function __construct(array $data)
    {
        $this->id = (int) ($data['id'] ?? 0);

        $this->userId = (int) ($data['user_id'] ?? 0);

        $this->shopId = isset($data['shop_id'])
            ? (int) $data['shop_id']
            : null;

        $this->experience = (int) ($data['experience'] ?? 0);

        $this->speciality = $data['speciality'] ?? null;

        $this->approvalStatus =
            $data['approval_status'] ?? 'pending';

        $this->approvedAt =
            $data['approved_at'] ?? null;

        $this->approvedBy =
            isset($data['approved_by'])
                ? (int) $data['approved_by']
                : null;

        $this->status =
            $data['status'] ?? 'inactive';
    }
}