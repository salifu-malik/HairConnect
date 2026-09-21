<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use PDO;

class PasswordResetRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection('users_db');
    }

    /**
     * Create a new password reset request.
     */
    public function create(
        ?int $userId,
        string $email,
        string $codeHash,
        string $expiresAt,
        string $lastSentAt
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO password_reset_requests (
                user_id,
                email,
                code_hash,
                expires_at,
                last_sent_at,
                attempts,
                blocked_until,
                verified_at,
                reset_token_hash,
                reset_token_expires_at
            )
            VALUES (
                :user_id,
                :email,
                :code_hash,
                :expires_at,
                :last_sent_at,
                0,
                NULL,
                NULL,
                NULL,
                NULL
            )
        ");

        $stmt->execute([
            'user_id' => $userId,
            'email' => $email,
            'code_hash' => $codeHash,
            'expires_at' => $expiresAt,
            'last_sent_at' => $lastSentAt
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Find the latest password reset request for an email.
     */
    public function findLatestByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM password_reset_requests
            WHERE email = :email
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            'email' => $email
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    /**
     * Find a password reset request by ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM password_reset_requests
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    /**
     * Increment failed verification attempts.
     */
    public function incrementAttempts(int $id): void
    {
        $stmt = $this->db->prepare("
            UPDATE password_reset_requests
            SET attempts = attempts + 1
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id
        ]);
    }

    /**
     * Block verification until a specific time.
     */
    public function block(
        int $id,
        string $blockedUntil
    ): void {
        $stmt = $this->db->prepare("
            UPDATE password_reset_requests
            SET blocked_until = :blocked_until
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'blocked_until' => $blockedUntil
        ]);
    }

    /**
     * Invalidate all previous password-reset requests
     * for an email address.
     *
     * This also invalidates previously generated reset tokens.
     */
    public function invalidatePreviousRequests(
        string $email
    ): void {
        $stmt = $this->db->prepare("
            UPDATE password_reset_requests
            SET
                expires_at = NOW(),
                reset_token_hash = NULL,
                reset_token_expires_at = NULL,
                verified_at = NULL,
                blocked_until = NULL,
                attempts = 0
            WHERE email = :email
        ");

        $stmt->execute([
            'email' => $email
        ]);
    }

    /**
     * Mark the verification code as successfully verified.
     */
    public function markVerified(int $id): void
    {
        $stmt = $this->db->prepare("
            UPDATE password_reset_requests
            SET verified_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id
        ]);
    }

    /**
     * Save the hashed password-reset token.
     */
    public function saveResetToken(
        int $id,
        string $tokenHash,
        string $expiresAt
    ): void {
        $stmt = $this->db->prepare("
            UPDATE password_reset_requests
            SET
                reset_token_hash = :token_hash,
                reset_token_expires_at = :expires_at
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt
        ]);
    }

    /**
     * Find a valid reset token.
     */
    public function findByResetTokenHash(
        string $tokenHash
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM password_reset_requests
            WHERE reset_token_hash = :token_hash
              AND reset_token_expires_at > NOW()
            LIMIT 1
        ");

        $stmt->execute([
            'token_hash' => $tokenHash
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    /**
     * Consume the password-reset request.
     *
     * This makes the reset token unusable.
     */
    public function consume(int $id): void
    {
        $stmt = $this->db->prepare("
            UPDATE password_reset_requests
            SET
                reset_token_hash = NULL,
                reset_token_expires_at = NULL
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id
        ]);
    }
}