<?php

namespace App\Helpers;

use App\Repositories\SubscriptionRepository;
use Exception;

class SubscriptionGuard
{
    private SubscriptionRepository $subscriptionRepository;

    public function __construct()
    {
        $this->subscriptionRepository =
            new SubscriptionRepository();
    }

    /**
     * Ensure the user has an active subscription.
     *
     * @throws Exception
     */
    public function requireActiveSubscription(
        int $userId
    ): void {
        $subscription =
            $this->subscriptionRepository
                ->findActiveByUserId($userId);

        if ($subscription === null) {
            throw new Exception(
                'An active subscription is required to use this feature.'
            );
        }
    }
}