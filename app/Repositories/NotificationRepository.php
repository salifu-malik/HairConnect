<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use PDO;

class NotificationRepository {
    private PDO $db;

    public function __construct() {
        $this->db = DatabaseManager::getConnection('notification_db');
    }

    public function createNotification(int $userId, string $title, string $message, string $type): int {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, title, message, type, status)
            VALUES (:user_id, :title, :message, :type, 'unread')
        ");
        $stmt->execute([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type
        ]);
        return (int)$this->db->lastInsertId();
    }
}
