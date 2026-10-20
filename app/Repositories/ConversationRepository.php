<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use PDO;
use RuntimeException;

class ConversationRepository
{
    private PDO $db;

    public function __construct(?PDO $connection = null)
    {
        $this->db = $connection
            ?? DatabaseManager::getConnection('messaging_db');
    }

    // Find a conversation by ID.
    public function findById(int $conversationId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM conversations
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute(['id' => $conversationId]);

        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

        return $conversation ?: null;
    }

     //Find a conversation only when the current user is a participant.
    public function findForParticipant(
        int $conversationId,
        int $userId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM conversations
            WHERE id = :id
              AND deleted_at IS NULL
              AND (
                  participant_one_id = :user_one
                  OR participant_two_id = :user_two
              )
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $conversationId,
            'user_one' => $userId,
            'user_two' => $userId,
        ]);

        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

        return $conversation ?: null;
    }

    // List conversations belonging to a user, including the other participant's details.
    public function findAllForParticipant(int $userId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            c.*,

            CASE
                WHEN c.participant_one_id = :current_user_name
                    THEN c.participant_two_id
                ELSE c.participant_one_id
            END AS recipient_id,

            (
                SELECT m.content
                FROM messages m
                WHERE m.conversation_id = c.id
                ORDER BY m.created_at DESC, m.id DESC
                LIMIT 1
            ) AS last_message,

            (
                SELECT m.created_at
                FROM messages m
                WHERE m.conversation_id = c.id
                ORDER BY m.created_at DESC, m.id DESC
                LIMIT 1
            ) AS last_message_at

        FROM conversations c

        WHERE c.deleted_at IS NULL
          AND (
              c.participant_one_id = :user_one
              OR c.participant_two_id = :user_two
          )
          AND (
              c.conversation_type = 'COMPLAINT'
              OR c.expires_at IS NULL
              OR c.expires_at > UTC_TIMESTAMP()
          )

        ORDER BY
            COALESCE(c.updated_at, c.created_at) DESC,
            c.id DESC
    ");

        $stmt->execute([
            'current_user_name' => $userId,
            'user_one' => $userId,
            'user_two' => $userId,
        ]);

        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$conversations) {
            return [];
        }

        // Fetch participant details from users_db.
        $usersDb = DatabaseManager::getConnection('users_db');

        $userIds = [];

        foreach ($conversations as $conversation) {
            $userIds[] = (int) $conversation['recipient_id'];
        }

        $userIds = array_values(array_unique($userIds));
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));

        $usersStmt = $usersDb->prepare("
        SELECT id, first_name, last_name, profile_image
        FROM users
        WHERE id IN ($placeholders)
    ");

        $usersStmt->execute($userIds);

        $usersById = [];

        foreach ($usersStmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
            $usersById[(int) $user['id']] = $user;
        }

        foreach ($conversations as &$conversation) {
            $recipientId = (int) $conversation['recipient_id'];
            $recipient = $usersById[$recipientId] ?? null;

            $conversation['recipient_name'] = $recipient
                ? trim($recipient['first_name'] . ' ' . $recipient['last_name'])
                : 'Unknown user';

            $conversation['recipientName'] = $conversation['recipient_name'];

            $conversation['recipient_profile_image'] =
                $recipient['profile_image'] ?? null;
        }

        unset($conversation);

        return $conversations;
    }

    /**
     * Find an existing business conversation for the same participants
     * and related order or appointment.
     *
     * Participant order does not matter.
     * Deleted conversations are not returned.
     */
    public function findExistingBusinessConversation(
        string $type,
        int $participantOneId,
        int $participantTwoId,
        ?int $relatedOrderId = null,
        ?int $relatedAppointmentId = null
    ): ?array {
        $allowedTypes = [
            'PRODUCT_ORDER',
            'HOME_SERVICE',
            'SHOP_SERVICE',
        ];

        if (!in_array($type, $allowedTypes, true)) {
            throw new RuntimeException(
                'Invalid business conversation type.'
            );
        }

        if (
            $participantOneId <= 0
            || $participantTwoId <= 0
            || $participantOneId === $participantTwoId
        ) {
            throw new RuntimeException(
                'Valid conversation participants are required.'
            );
        }

        if (
            $type === 'PRODUCT_ORDER'
            && ($relatedOrderId === null || $relatedOrderId <= 0)
        ) {
            throw new RuntimeException(
                'A valid order ID is required.'
            );
        }

        if (
            in_array($type, ['HOME_SERVICE', 'SHOP_SERVICE'], true)
            && (
                $relatedAppointmentId === null
                || $relatedAppointmentId <= 0
            )
        ) {
            throw new RuntimeException(
                'A valid appointment ID is required.'
            );
        }

        $stmt = $this->db->prepare("
        SELECT *
        FROM conversations
        WHERE conversation_type = :type
          AND deleted_at IS NULL
          AND (
              (
                  participant_one_id = :participant_one
                  AND participant_two_id = :participant_two
              )
              OR
              (
                  participant_one_id = :participant_two_reverse
                  AND participant_two_id = :participant_one_reverse
              )
          )
          AND related_order_id <=> :order_id
          AND related_appointment_id <=> :appointment_id
          AND (
              conversation_type = 'COMPLAINT'
              OR expires_at IS NULL
              OR expires_at > UTC_TIMESTAMP()
          )
        ORDER BY id DESC
        LIMIT 1
    ");

        $stmt->execute([
            'type' => $type,
            'participant_one' => $participantOneId,
            'participant_two' => $participantTwoId,
            'participant_two_reverse' => $participantTwoId,
            'participant_one_reverse' => $participantOneId,
            'order_id' => $relatedOrderId,
            'appointment_id' => $relatedAppointmentId,
        ]);

        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

        return $conversation ?: null;
    }


    /**
     * Create a conversation.
     * Business conversations must be created by the service layer
     * only after validating the relevant order or appointment.
     */
    public function create(array $data): int
    {
        $type = $data['conversation_type'] ?? '';
        $participantOne = (int) ($data['participant_one_id'] ?? 0);
        $participantTwo = (int) ($data['participant_two_id'] ?? 0);
        $createdBy = (int) ($data['created_by'] ?? 0);

        $allowedTypes = [
            'PRODUCT_ORDER',
            'HOME_SERVICE',
            'SHOP_SERVICE',
            'COMPLAINT',
        ];

        if (!in_array($type, $allowedTypes, true)) {
            throw new RuntimeException('Invalid conversation type.');
        }

        if (
            $participantOne <= 0
            || $participantTwo <= 0
            || $participantOne === $participantTwo
            || $createdBy <= 0
        ) {
            throw new RuntimeException(
                'Valid conversation participants are required.'
            );
        }

        if (
            $type === 'PRODUCT_ORDER'
            && empty($data['related_order_id'])
        ) {
            throw new RuntimeException(
                'A product-order conversation requires an order.'
            );
        }

        if (
            in_array($type, ['HOME_SERVICE', 'SHOP_SERVICE'], true)
            && empty($data['related_appointment_id'])
        ) {
            throw new RuntimeException(
                'A service conversation requires an appointment.'
            );
        }

        $stmt = $this->db->prepare("
            INSERT INTO conversations (
                conversation_type,
                participant_one_id,
                participant_two_id,
                related_order_id,
                related_appointment_id,
                created_by
            ) VALUES (
                :type,
                :participant_one,
                :participant_two,
                :order_id,
                :appointment_id,
                :created_by
            )
        ");

        $stmt->execute([
            'type' => $type,
            'participant_one' => $participantOne,
            'participant_two' => $participantTwo,
            'order_id' => $data['related_order_id'] ?? null,
            'appointment_id' => $data['related_appointment_id'] ?? null,
            'created_by' => $createdBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    // Lock a conversation row inside an active transaction.
    public function lockById(int $conversationId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM conversations
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute(['id' => $conversationId]);

        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

        return $conversation ?: null;
    }

    /**
     * Set the first-message timestamp and fixed expiry.
     * Call this only while the conversation row is locked
     * inside a transaction.
     */
    public function setFirstMessageExpiry(int $conversationId): void
    {
        $stmt = $this->db->prepare("
        UPDATE conversations
        SET
            first_message_at = UTC_TIMESTAMP(),
            expires_at = DATE_ADD(
                UTC_TIMESTAMP(),
                INTERVAL 48 HOUR
            )
        WHERE id = :id
          AND first_message_at IS NULL
          AND expires_at IS NULL
          AND conversation_type <> 'COMPLAINT'
    ");

        $stmt->execute(['id' => $conversationId]);
    }

    //Update the conversation activity timestamp.
    public function touch(int $conversationId): void
    {
        $stmt = $this->db->prepare("
            UPDATE conversations
            SET updated_at = UTC_TIMESTAMP()
            WHERE id = :id
              AND deleted_at IS NULL
        ");

        $stmt->execute(['id' => $conversationId]);
    }
}