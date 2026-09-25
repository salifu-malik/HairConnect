<?php

namespace App\Models;

class Subscription
{
    public int $id;
    public int $userId;
    public int $planId;
    public ?int $planVersionId;
    public string $startDate;
    public string $endDate;
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

        $this->startDate = $data['start_date'];
        $this->endDate = $data['end_date'];
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
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'status' => $this->status,
            'created_at' => $this->createdAt,
        ];
    }
}