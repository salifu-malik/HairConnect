<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use PDO;
use RuntimeException;

class MessageRepository
{
    private PDO $db;

    public function __construct(?PDO $connection = null)
    {
        $this->db = $connection
            ?? DatabaseManager::getConnection('messaging_db');
    }

    /**
     * Save a message.
     * The caller must validate the sender, participant access,
     * conversation expiry, and first-message timing.
     */
    public function create(
        int $conversationId,
        int $senderId,
        string $content
    ): int {
        $content = trim($content);

        if ($conversationId <= 0 || $senderId <= 0) {
            throw new RuntimeException(
                'A valid conversation and sender are required.'
            );
        }

        if ($content === '') {
            throw new RuntimeException(
                'Message content cannot be empty.'
            );
        }

        if (mb_strlen($content) > 5000) {
            throw new RuntimeException(
                'Message cannot exceed 5000 characters.'
            );
        }

        $stmt = $this->db->prepare("
            INSERT INTO messages (
                conversation_id,
                sender_id,
                message_type,
                content
            ) VALUES (
                :conversation_id,
                :sender_id,
                'TEXT',
                :content
            )
        ");

        $stmt->execute([
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'content' => $content,
        ]);

        return (int) $this->db->lastInsertId();
    }

     //Retrieve messages in chronological order.
    public function findByConversationId(
        int $conversationId,
        int $limit = 100,
        int $beforeId = 0
    ): array {
        $limit = max(1, min($limit, 100));
        $beforeId = max(0, $beforeId);

        if ($beforeId > 0) {
            $stmt = $this->db->prepare("
                SELECT
    id,
    conversation_id,
    sender_id,
    message_type,
    content,
    created_at,
    delivered_at,
    read_at
                FROM messages
                WHERE conversation_id = :conversation_id
                  AND id < :before_id
                ORDER BY id DESC
                LIMIT {$limit}
            ");

            $stmt->execute([
                'conversation_id' => $conversationId,
                'before_id' => $beforeId,
            ]);
        } else {
            $stmt = $this->db->prepare("
                SELECT
    id,
    conversation_id,
    sender_id,
    message_type,
    content,
    created_at,
    delivered_at,
    read_at

                FROM messages
                WHERE conversation_id = :conversation_id
                ORDER BY id DESC
                LIMIT {$limit}
            ");

            $stmt->execute([
                'conversation_id' => $conversationId,
            ]);
        }

        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function markAsDelivered(int $conversationId, int $recipientId): int
    {
        $stmt = $this->db->prepare("
        UPDATE messages
        SET delivered_at = UTC_TIMESTAMP()
        WHERE conversation_id = :conversation_id
          AND sender_id <> :recipient_id
          AND delivered_at IS NULL
    ");

        $stmt->execute([
            'conversation_id' => $conversationId,
            'recipient_id' => $recipientId,
        ]);

        return $stmt->rowCount();
    }


    public function markAsRead(int $conversationId, int $recipientId): int
    {
        $stmt = $this->db->prepare("
        UPDATE messages
        SET
            delivered_at = COALESCE(delivered_at, UTC_TIMESTAMP()),
            read_at = UTC_TIMESTAMP()
        WHERE conversation_id = :conversation_id
          AND sender_id <> :recipient_id
          AND read_at IS NULL
    ");

        $stmt->execute([
            'conversation_id' => $conversationId,
            'recipient_id' => $recipientId,
        ]);

        return $stmt->rowCount();
    }

}