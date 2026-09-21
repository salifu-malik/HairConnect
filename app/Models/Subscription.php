<?php

namespace App\Models;

class Subscription {
    public int $id;
    public int $userId;
    public int $planId;
    public string $startDate;
    public string $endDate;
    public string $status;

    public function __construct(array $data) {
        $this->id = $data['id'] ?? 0;
        $this->userId = $data['user_id'] ?? 0;
        $this->planId = $data['plan_id'] ?? 0;
        $this->startDate = $data['start_date'] ?? '';
        $this->endDate = $data['end_date'] ?? '';
        $this->status = $data['status'] ?? 'active';
    }
}
