<?php

namespace App\Models;

class SubscriptionPaymentAttempt
{
    public int $id;
    public int $paymentId;
    public int $attemptNumber;
    public string $reference;
    public string $status;
    public string $reconciliationStatus;
    public ?string $authorizationUrl;
    public ?string $accessCode;
    public ?string $failureReason;
    public ?string $initializedAt;
    public ?string $verifiedAt;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->paymentId = (int) $data['payment_id'];
        $this->attemptNumber = (int) $data['attempt_number'];

        $this->reference = $data['reference'];
        $this->status = $data['status'];

        $this->reconciliationStatus =
            $data['reconciliation_status'];

        $this->authorizationUrl =
            $data['authorization_url'] ?? null;

        $this->accessCode =
            $data['access_code'] ?? null;

        $this->failureReason =
            $data['failure_reason'] ?? null;

        $this->initializedAt =
            $data['initialized_at'] ?? null;

        $this->verifiedAt =
            $data['verified_at'] ?? null;

        $this->createdAt = $data['created_at'];
        $this->updatedAt = $data['updated_at'];
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->paymentId,
            'attempt_number' => $this->attemptNumber,
            'reference' => $this->reference,
            'status' => $this->status,
            'reconciliation_status' =>
                $this->reconciliationStatus,
            'authorization_url' => $this->authorizationUrl,
            'access_code' => $this->accessCode,
            'failure_reason' => $this->failureReason,
            'initialized_at' => $this->initializedAt,
            'verified_at' => $this->verifiedAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}