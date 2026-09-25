<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use App\Models\SubscriptionPayment;
use PDO;

class SubscriptionPaymentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection(
            'subscription_db'
        );
    }

    public function create(
        int $subscriptionId,
        float $amount,
        string $currency,
        string $reference
    ): ?SubscriptionPayment {
        $stmt = $this->db->prepare("
            INSERT INTO subscription_payments (
                subscription_id,
                amount,
                currency,
                reference,
                status
            )
            VALUES (
                :subscription_id,
                :amount,
                :currency,
                :reference,
                'pending'
            )
        ");

        $result = $stmt->execute([
            'subscription_id' => $subscriptionId,
            'amount' => $amount,
            'currency' => $currency,
            'reference' => $reference
        ]);

        if (!$result) {
            return null;
        }

        $id = (int) $this->db->lastInsertId();

        return $this->findById($id);
    }

    public function findById(
        int $paymentId
    ): ?SubscriptionPayment {
        $stmt = $this->db->prepare("
            SELECT *
            FROM subscription_payments
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $paymentId
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return new SubscriptionPayment($data);
    }

    public function findByReference(
        string $reference
    ): ?SubscriptionPayment {
        $stmt = $this->db->prepare("
            SELECT *
            FROM subscription_payments
            WHERE reference = :reference
            LIMIT 1
        ");

        $stmt->execute([
            'reference' => $reference
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return new SubscriptionPayment($data);
    }

    public function updateStatus(
        int $paymentId,
        string $status
    ): bool {
        $paidAt = $status === 'completed'
            ? date('Y-m-d H:i:s')
            : null;

        $stmt = $this->db->prepare("
            UPDATE subscription_payments
            SET
                status = :status,
                paid_at = :paid_at
            WHERE id = :id
        ");

        return $stmt->execute([
            'status' => $status,
            'paid_at' => $paidAt,
            'id' => $paymentId
        ]);
    }

    public function findBySubscriptionId(
        int $subscriptionId
    ): array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM subscription_payments
            WHERE subscription_id = :subscription_id
            ORDER BY created_at DESC
        ");

        $stmt->execute([
            'subscription_id' => $subscriptionId
        ]);

        $payments = [];

        foreach (
            $stmt->fetchAll(PDO::FETCH_ASSOC)
            as $data
        ) {
            $payments[] = new SubscriptionPayment($data);
        }

        return $payments;
    }
}