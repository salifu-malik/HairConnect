<?php

namespace App\Models;

class SubscriptionPayment
{
    public int $id;
    public int $subscriptionId;
    public float $amount;
    public string $currency;
    public string $reference;
    public string $status;
    public ?string $paidAt;
    public string $createdAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->subscriptionId =
            (int) $data['subscription_id'];

        $this->amount = (float) $data['amount'];
        $this->currency = $data['currency'];
        $this->reference = $data['reference'];
        $this->status = $data['status'];

        $this->paidAt = $data['paid_at'] ?? null;
        $this->createdAt = $data['created_at'];
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'subscription_id' => $this->subscriptionId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reference' => $this->reference,
            'status' => $this->status,
            'paid_at' => $this->paidAt,
            'created_at' => $this->createdAt,
        ];
    }
}