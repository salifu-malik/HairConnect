<?php

namespace App\Models;

class SubscriptionPayment {
    public int $id;
    public int $subscriptionId;
    public float $amount;
    public string $reference;
    public string $status;

    public function __construct(array $data) {
        $this->id = $data['id'] ?? 0;
        $this->subscriptionId = $data['subscription_id'] ?? 0;
        $this->amount = (float)($data['amount'] ?? 0.0);
        $this->reference = $data['reference'] ?? '';
        $this->status = $data['status'] ?? 'pending';
    }
}
