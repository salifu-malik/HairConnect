<?php

namespace App\Services;

use App\Repositories\SubscriptionRepository;

class SubscriptionService {
    private SubscriptionRepository $repository;

    public function __construct() {
        $this->repository = new SubscriptionRepository();
    }

    public function subscribe(int $userId, int $planId): bool {
        $plan = $this->repository->getPlanById($planId);
        if (!$plan) {
            return false;
        }

        $endDate = date('Y-m-d H:i:s', strtotime('+1 month')); // Simplified billing period logic

        $this->repository->createSubscription([
            'user_id' => $userId,
            'plan_id' => $planId,
            'end_date' => $endDate,
            'status' => 'active'
        ]);

        return true;
    }
}
