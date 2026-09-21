<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use PDO;

class SessionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection('users_db');
    }

    public function save(
        int $userId,
        string $refreshToken,
        string $expiresAt
    ): void {
        $tokenHash = hash('sha256', $refreshToken);

        $stmt = $this->db->prepare("
            INSERT INTO sessions (
                user_id,
                refresh_token,
                expires_at
            )
            VALUES (
                :user_id,
                :refresh_token,
                :expires_at
            )
        ");

        $stmt->execute([
            'user_id' => $userId,
            'refresh_token' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
    }

    public function findByToken(
        string $refreshToken
    ): ?array {
        $tokenHash = hash('sha256', $refreshToken);

        $stmt = $this->db->prepare("
            SELECT *
            FROM sessions
            WHERE refresh_token = :refresh_token
              AND expires_at > NOW()
            LIMIT 1
        ");

        $stmt->execute([
            'refresh_token' => $tokenHash
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    public function deleteByToken(
        string $refreshToken
    ): void {
        $tokenHash = hash('sha256', $refreshToken);

        $stmt = $this->db->prepare("
            DELETE FROM sessions
            WHERE refresh_token = :refresh_token
        ");

        $stmt->execute([
            'refresh_token' => $tokenHash
        ]);
    }


    public function deleteAllForUser(int $userId): void
    {
        $stmt = $this->db->prepare("
        DELETE FROM sessions
        WHERE user_id = :user_id
    ");

        $stmt->execute([
            'user_id' => $userId
        ]);
    }
}