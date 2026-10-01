<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use PDO;
use Throwable;

class AuditLogRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection('audit_db');
    }

    public function create(array $data): int
    {
        $sql = "
            INSERT INTO audit_logs (
                user_id,
                user_email,
                user_role,
                action,
                module,
                entity_type,
                entity_id,
                description,
                ip_address,
                user_agent,
                metadata
            ) VALUES (
                :user_id,
                :user_email,
                :user_role,
                :action,
                :module,
                :entity_type,
                :entity_id,
                :description,
                :ip_address,
                :user_agent,
                :metadata
            )
        ";

        $stmt = $this->db->prepare($sql);

        $metadata = $data['metadata'] ?? null;

        if (is_array($metadata)) {
            $metadata = json_encode(
                $metadata,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        }

        $stmt->execute([
            ':user_id' => $data['user_id'] ?? null,
            ':user_email' => $data['user_email'] ?? null,
            ':user_role' => $data['user_role'] ?? null,
            ':action' => $data['action'],
            ':module' => $data['module'],
            ':entity_type' => $data['entity_type'] ?? null,
            ':entity_id' => $data['entity_id'] ?? null,
            ':description' => $data['description'] ?? null,
            ':ip_address' => $data['ip_address'] ?? null,
            ':user_agent' => $data['user_agent'] ?? null,
            ':metadata' => $metadata,
        ]);

        return (int) $this->db->lastInsertId();
    }
}