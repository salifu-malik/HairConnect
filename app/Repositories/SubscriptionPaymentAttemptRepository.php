<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use App\Models\SubscriptionPaymentAttempt;
use PDO;

class SubscriptionPaymentAttemptRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection(
            'subscription_db'
        );
    }

    /**
     * Create a new Paystack payment attempt.
     */
    public function create(
        int $paymentId,
        int $attemptNumber,
        string $reference
    ): ?SubscriptionPaymentAttempt {
        $stmt = $this->db->prepare("
            INSERT INTO subscription_payment_attempts (
                payment_id,
                attempt_number,
                reference,
                status
            )
            VALUES (
                :payment_id,
                :attempt_number,
                :reference,
                'initializing'
            )
        ");

        $result = $stmt->execute([
            'payment_id' => $paymentId,
            'attempt_number' => $attemptNumber,
            'reference' => $reference
        ]);

        if (!$result) {
            return null;
        }

        return $this->findById(
            (int) $this->db->lastInsertId()
        );
    }

    /**
     * Find an attempt by its primary key.
     */
    public function findById(
        int $attemptId
    ): ?SubscriptionPaymentAttempt {
        $stmt = $this->db->prepare("
            SELECT *
            FROM subscription_payment_attempts
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $attemptId
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data
            ? new SubscriptionPaymentAttempt($data)
            : null;
    }

    /**
     * Find an attempt using its Paystack reference.
     */
    public function findByReference(
        string $reference
    ): ?SubscriptionPaymentAttempt {
        $stmt = $this->db->prepare("
            SELECT *
            FROM subscription_payment_attempts
            WHERE reference = :reference
            LIMIT 1
        ");

        $stmt->execute([
            'reference' => $reference
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data
            ? new SubscriptionPaymentAttempt($data)
            : null;
    }

    /**
     * Find and lock an attempt by its Paystack reference.
     *
     * Must be called inside an active transaction.
     */
    public function findByReferenceForUpdate(
        string $reference
    ): ?SubscriptionPaymentAttempt {
        $stmt = $this->db->prepare("
            SELECT *
            FROM subscription_payment_attempts
            WHERE reference = :reference
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            'reference' => $reference
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data
            ? new SubscriptionPaymentAttempt($data)
            : null;
    }

    /**
     * Retrieve all attempts for a payment.
     */
    public function findByPaymentId(
        int $paymentId
    ): array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM subscription_payment_attempts
            WHERE payment_id = :payment_id
            ORDER BY attempt_number ASC
        ");

        $stmt->execute([
            'payment_id' => $paymentId
        ]);

        $attempts = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $data) {
            $attempts[] = new SubscriptionPaymentAttempt($data);
        }

        return $attempts;
    }

    /**
     * Find an active checkout attempt.
     *
     * Initializing and pending attempts are considered active.
     */
    public function findActiveByPaymentId(
        int $paymentId
    ): ?SubscriptionPaymentAttempt {
        $stmt = $this->db->prepare("
            SELECT *
            FROM subscription_payment_attempts
            WHERE payment_id = :payment_id
AND status IN (
    'initializing',
    'initialization_unknown',
    'pending'
) ORDER BY attempt_number DESC
            LIMIT 1
        ");

        $stmt->execute([
            'payment_id' => $paymentId
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data
            ? new SubscriptionPaymentAttempt($data)
            : null;
    }

    /**
     * Determine the next attempt number.
     *
     * Call while holding the parent payment row lock.
     */
    public function findNextAttemptNumber(
        int $paymentId
    ): int {
        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(attempt_number), 0) + 1
            FROM subscription_payment_attempts
            WHERE payment_id = :payment_id
        ");

        $stmt->execute([
            'payment_id' => $paymentId
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Update checkout details after Paystack initialization.
     */
    public function updateCheckoutDetails(
        int $attemptId,
        string $authorizationUrl,
        ?string $accessCode
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE subscription_payment_attempts
            SET
                authorization_url = :authorization_url,
                access_code = :access_code,
                status = 'pending',
                initialized_at = UTC_TIMESTAMP(),
                failure_reason = NULL
            WHERE id = :id
              AND status = 'initializing'
        ");

        $stmt->execute([
            'authorization_url' => $authorizationUrl,
            'access_code' => $accessCode,
            'id' => $attemptId
        ]);

        return $stmt->rowCount() === 1;
    }
    /**
     * Update an attempt while enforcing valid state transitions.
     *
     * A succeeded attempt is terminal.
     * A failed or abandoned attempt may become succeeded
     * if Paystack later confirms a successful transaction.
     */
    public function updateStatus(
        int $attemptId,
        string $status,
        ?string $failureReason = null,
        bool $verified = false
    ): bool {
        $allowedStatuses = [
            'initializing',
            'initialization_unknown',
            'pending',
            'succeeded',
            'failed',
            'abandoned'
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            throw new \InvalidArgumentException(
                'Invalid payment attempt status.'
            );
        }

        if ($status === 'succeeded') {
            $verified = true;
            $failureReason = null;
        }

        $stmt = $this->db->prepare("
        UPDATE subscription_payment_attempts
        SET
            status = :new_status,
            failure_reason = :failure_reason,
            verified_at = CASE
                WHEN :is_verified = 1
                THEN UTC_TIMESTAMP()
                ELSE verified_at
            END
        WHERE id = :id
          AND (
                (
                    status = 'initializing'
                    AND :initializing_status IN (
                        'pending',
                        'initialization_unknown',
                        'succeeded',
                        'failed',
                        'abandoned'
                    )
                )
                OR
                (
                    status = 'pending'
                    AND :pending_status IN (
                        'succeeded',
                        'failed',
                        'abandoned'
                    )
                )
                OR
                (
                    status = 'initialization_unknown'
                    AND :unknown_status IN (
                        'succeeded',
                        'failed'
                    )
                )
                OR
                (
                    status IN ('failed', 'abandoned')
                    AND :late_success_status = 'succeeded'
                )
          )
    ");

        $stmt->execute([
            'new_status' => $status,
            'failure_reason' => $failureReason,
            'is_verified' => $verified ? 1 : 0,
            'id' => $attemptId,
            'initializing_status' => $status,
            'unknown_status' => $status,
            'pending_status' => $status,
            'late_success_status' => $status
        ]);

        return $stmt->rowCount() === 1;
    }


    /**
     * Update reconciliation status while enforcing valid transitions.
     *
     * Repeated transitions are idempotent.
     * Refunded and resolved are terminal states.
     */
    public function updateReconciliationStatus(
        int $attemptId,
        string $reconciliationStatus
    ): bool {
        $allowedStatuses = [
            'not_required',
            'pending_review',
            'refund_required',
            'refunded',
            'resolved'
        ];

        if (!in_array(
            $reconciliationStatus,
            $allowedStatuses,
            true
        )) {
            throw new \InvalidArgumentException(
                'Invalid payment reconciliation status.'
            );
        }

        $stmt = $this->db->prepare("
        UPDATE subscription_payment_attempts
        SET reconciliation_status = :new_status
        WHERE id = :id
          AND (
              (
                  reconciliation_status = 'not_required'
                  AND :from_not_required IN (
                      'pending_review',
                      'refund_required'
                  )
              )
              OR
              (
                  reconciliation_status = 'pending_review'
                  AND :from_pending_review IN (
                      'refund_required',
                      'refunded',
                      'resolved'
                  )
              )
              OR
              (
                  reconciliation_status = 'refund_required'
                  AND :from_refund_required IN (
                      'refunded',
                      'resolved'
                  )
              )
              OR
(
    reconciliation_status = 'refunded'
    AND :from_refunded = 'resolved'
)
          )
    ");

        $stmt->execute([
            'new_status' => $reconciliationStatus,
            'id' => $attemptId,
            'from_not_required' => $reconciliationStatus,
            'from_pending_review' => $reconciliationStatus,
            'from_refund_required' => $reconciliationStatus,
            'from_refunded' => $reconciliationStatus
        ]);

        if ($stmt->rowCount() === 1) {
            return true;
        }

        // Treat an already-applied transition as successful.
        $current = $this->findById($attemptId);

        return $current !== null
            && $current->reconciliationStatus === $reconciliationStatus;
    }
}
