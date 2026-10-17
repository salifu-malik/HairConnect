<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use PDO;

class NotificationRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection('notification_db');
    }

    /**
     * Create a notification for a user.
     */
    public function createNotification(
        int $userId,
        string $title,
        string $message,
        string $type
    ): int {
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

        return (int) $this->db->lastInsertId();
    }

    /**
     * Retrieve notifications belonging to a specific user.
     */
    public function getNotificationsByUser(
        int $userId,
        int $limit = 20,
        int $offset = 0
    ): array {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $stmt = $this->db->prepare("
            SELECT
                id,
                title,
                message,
                type,
                status,
                created_at
            FROM notifications
            WHERE user_id = :user_id
            ORDER BY created_at DESC, id DESC
            LIMIT :limit OFFSET :offset
        ");

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count unread notifications belonging to a specific user.
     */
    public function getUnreadCount(int $userId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = :user_id
              AND status = 'unread'
        ");

        $stmt->execute([
            'user_id' => $userId
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Mark a notification as read.
     * A user can only update their own notification.
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE notifications
            SET status = 'read'
            WHERE id = :notification_id
              AND user_id = :user_id
              AND status = 'unread'
        ");

        $stmt->execute([
            'notification_id' => $notificationId,
            'user_id' => $userId
        ]);

        return $stmt->rowCount() > 0;
    }
}
