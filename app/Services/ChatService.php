<?php

namespace App\Services;

use App\Helpers\DatabaseManager;
use App\Repositories\ConversationRepository;
use App\Repositories\MessageRepository;
use PDO;
use RuntimeException;
use Throwable;

class ChatService
{
    private PDO $db;
    private PDO $usersDb;
    private PDO $storeDb;
    private PDO $bookingDb;

    private ConversationRepository $conversationRepository;
    private MessageRepository $messageRepository;

    public function __construct(
        ?PDO $connection = null,
        ?ConversationRepository $conversationRepository = null,
        ?MessageRepository $messageRepository = null
    ) {
        $this->db = $connection
            ?? DatabaseManager::getConnection('messaging_db');

        $this->usersDb = DatabaseManager::getConnection('users_db');
        $this->storeDb = DatabaseManager::getConnection('store_db');
        $this->bookingDb = DatabaseManager::getConnection('booking_db');

        $this->conversationRepository = $conversationRepository
            ?? new ConversationRepository($this->db);

        $this->messageRepository = $messageRepository
            ?? new MessageRepository($this->db);
    }
    //List conversations visible to the authenticated user.
    public function getConversations(int $userId): array
    {
        $this->requireValidUserId($userId);

        return $this->conversationRepository
            ->findAllForParticipant($userId);
    }

    /**
     * Return contacts the authenticated user is eligible to message.
     * Business contacts are based on existing orders or appointments.
     * Finance Managers are available to customers even without a conversation.
     */
    public function getContacts(int $userId): array
    {
        $this->requireValidUserId($userId);

        $userStmt = $this->usersDb->prepare("
        SELECT id
        FROM users
        WHERE id = :id
          AND status = 'active'
        LIMIT 1
    ");

        $userStmt->execute(['id' => $userId]);

        if (!$userStmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Active user not found.');
        }

        $rolesStmt = $this->usersDb->prepare("
        SELECT r.name
        FROM user_roles ur
        INNER JOIN roles r ON r.id = ur.role_id
        WHERE ur.user_id = :user_id
    ");

        $rolesStmt->execute(['user_id' => $userId]);
        $roles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);

        $contacts = [];

        // Customers can always see active Finance Managers.
        if (in_array('CUSTOMER', $roles, true)) {
            $financeStmt = $this->usersDb->query("
            SELECT
                u.public_id AS user_id,
                u.first_name,
                u.last_name,
                u.profile_image
            FROM users u
            INNER JOIN user_roles ur ON ur.user_id = u.id
            INNER JOIN roles r ON r.id = ur.role_id
            WHERE u.status = 'active'
              AND r.name = 'FINANCE_MANAGER'
            ORDER BY u.first_name, u.last_name
        ");

            foreach ($financeStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $contacts[] = [
                    'userId' => (string) $row['user_id'],
                    'name' => trim(
                        $row['first_name'] . ' ' . $row['last_name']
                    ),
                    'profileImage' => $row['profile_image'],
                    'role' => 'FINANCE_MANAGER',
                    'contactType' => 'COMPLAINT',
                    'relatedId' => null,
                ];
            }
        }

        // Only customers discover business contacts through their own activity.
        if (!in_array('CUSTOMER', $roles, true)) {
            return $contacts;
        }

        /*
         * Vendor contacts:
         * The customer's order must contain at least one product
         * belonging to the vendor.
         */
        $vendorStmt = $this->storeDb->prepare("
    SELECT DISTINCT
        u.public_id AS user_id,
        u.first_name,
        u.last_name,
        u.profile_image,
        o.id AS related_id
    FROM orders o
    INNER JOIN order_items oi ON oi.order_id = o.id
    INNER JOIN products p ON p.id = oi.product_id
    INNER JOIN users_db.users u ON u.id = p.seller_id
    WHERE o.customer_id = :customer_id
      AND o.status <> 'cancelled'
      AND u.status = 'active'
    ORDER BY u.first_name, u.last_name
");

        $vendorStmt->execute(['customer_id' => $userId]);

        foreach ($vendorStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $contacts[] = [
                'userId' => (string) $row['user_id'],
                'name' => trim(
                    $row['first_name'] . ' ' . $row['last_name']
                ),
                'profileImage' => $row['profile_image'],
                'role' => 'VENDOR',
                'contactType' => 'PRODUCT_ORDER',
                'relatedId' => (int) $row['related_id'],
            ];
        }

        /*
         * Independent barbers:
         * Only home-service appointments with an independent barber qualify.
         */
        $barberStmt = $this->bookingDb->prepare("
        SELECT DISTINCT
            u.public_id AS user_id,
            u.first_name,
            u.last_name,
            u.profile_image,
            a.id AS related_id
        FROM appointments a
        INNER JOIN barbers b ON b.id = a.barber_id
        INNER JOIN users_db.users u ON u.id = b.user_id
        WHERE a.customer_id = :customer_id
          AND a.service_location = 'HOME'
          AND b.shop_id IS NULL
          AND a.status NOT IN ('cancelled', 'rejected', 'no_show')
          AND u.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");

        $barberStmt->execute(['customer_id' => $userId]);

        foreach ($barberStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $contacts[] = [
                'userId' => (string) $row['user_id'],
                'name' => trim(
                    $row['first_name'] . ' ' . $row['last_name']
                ),
                'profileImage' => $row['profile_image'],
                'role' => 'BARBER',
                'contactType' => 'HOME_SERVICE',
                'relatedId' => (int) $row['related_id'],
            ];
        }

        /*
         * Shop owners:
         * Only shop-service appointments qualify.
         */
        $shopStmt = $this->bookingDb->prepare("
        SELECT DISTINCT
            u.public_id AS user_id,
            u.first_name,
            u.last_name,
            u.profile_image,
            a.id AS related_id
        FROM appointments a
        INNER JOIN shops s ON s.id = a.shop_id
        INNER JOIN users_db.users u ON u.id = s.owner_id
        WHERE a.customer_id = :customer_id
          AND a.service_location = 'SHOP'
          AND a.shop_id IS NOT NULL
          AND a.status NOT IN ('cancelled', 'rejected', 'no_show')
          AND u.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");

        $shopStmt->execute(['customer_id' => $userId]);

        foreach ($shopStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $contacts[] = [
                'userId' => (string) $row['user_id'],
                'name' => trim(
                    $row['first_name'] . ' ' . $row['last_name']
                ),
                'profileImage' => $row['profile_image'],
                'role' => 'SHOP_OWNER',
                'contactType' => 'SHOP_SERVICE',
                'relatedId' => (int) $row['related_id'],
            ];
        }

        return $contacts;
    }

    /**
     * Create or retrieve a business conversation.
     * The related record and recipient must be verified against the
     * authenticated customer's order or appointment.
     * @return array{id:int, conversation_type:string, created:bool}
     */
    public function startBusinessConversation(
        int $userId,
        string $type,
        int $recipientId,
        int $relatedId
    ): array
    {
        $this->requireValidUserId($userId);
        $this->requireValidUserId($recipientId);

        if ($userId === $recipientId || $relatedId <= 0) {
            throw new RuntimeException('Invalid conversation details.');
        }

        $allowedTypes = [
            'PRODUCT_ORDER',
            'HOME_SERVICE',
            'SHOP_SERVICE',
        ];

        if (!in_array($type, $allowedTypes, true)) {
            throw new RuntimeException('Invalid business conversation type.');
        }

        // Verify that the authenticated customer is active and has
        // the CUSTOMER role.
        $customerStmt = $this->usersDb->prepare("
        SELECT u.id
        FROM users u
        INNER JOIN user_roles ur ON ur.user_id = u.id
        INNER JOIN roles r ON r.id = ur.role_id
        WHERE u.id = :user_id
          AND u.status = 'active'
          AND r.name = 'CUSTOMER'
        LIMIT 1
    ");

        $customerStmt->execute(['user_id' => $userId]);

        if (!$customerStmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException(
                'Only active customers can initiate business conversations.'
            );
        }

        // Verify the recipient is active and has the required role.
        $requiredRole = match ($type) {
            'PRODUCT_ORDER' => 'VENDOR',
            'HOME_SERVICE' => 'BARBER',
            'SHOP_SERVICE' => 'SHOP_OWNER',
        };

        $recipientStmt = $this->usersDb->prepare("
        SELECT u.id
        FROM users u
        INNER JOIN user_roles ur ON ur.user_id = u.id
        INNER JOIN roles r ON r.id = ur.role_id
        WHERE u.id = :user_id
          AND u.status = 'active'
          AND r.name = :role
        LIMIT 1
    ");

        $recipientStmt->execute([
            'user_id' => $recipientId,
            'role' => $requiredRole,
        ]);

        if (!$recipientStmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException(
                'The recipient is not an active user with the required role.'
            );
        }

        $relatedOrderId = null;
        $relatedAppointmentId = null;

        if ($type === 'PRODUCT_ORDER') {
            // Verify the customer owns the order and that it contains
            // at least one product belonging to the selected vendor.
            $stmt = $this->storeDb->prepare("
            SELECT o.id
            FROM orders o
            INNER JOIN order_items oi ON oi.order_id = o.id
            INNER JOIN products p ON p.id = oi.product_id
            WHERE o.id = :order_id
              AND o.customer_id = :customer_id
              AND p.seller_id = :vendor_id
              AND o.status <> 'cancelled'
            LIMIT 1
        ");

            $stmt->execute([
                'order_id' => $relatedId,
                'customer_id' => $userId,
                'vendor_id' => $recipientId,
            ]);

            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException(
                    'The order does not authorize contact with this vendor.'
                );
            }

            $relatedOrderId = $relatedId;
        } else {
            // Verify the appointment belongs to the customer and the
            // selected recipient is the correct service provider.
            if ($type === 'HOME_SERVICE') {
                $stmt = $this->bookingDb->prepare("
                SELECT a.id
                FROM appointments a
                INNER JOIN barbers b ON b.id = a.barber_id
                WHERE a.id = :appointment_id
                  AND a.customer_id = :customer_id
                  AND a.service_location = 'HOME'
                  AND b.user_id = :barber_user_id
                  AND b.shop_id IS NULL
                  AND a.status NOT IN (
                      'cancelled', 'rejected', 'no_show'
                  )
                LIMIT 1
            ");

                $stmt->execute([
                    'appointment_id' => $relatedId,
                    'customer_id' => $userId,
                    'barber_user_id' => $recipientId,
                ]);
            } else {
                $stmt = $this->bookingDb->prepare("
                SELECT a.id
                FROM appointments a
                INNER JOIN shops s ON s.id = a.shop_id
                WHERE a.id = :appointment_id
                  AND a.customer_id = :customer_id
                  AND a.service_location = 'SHOP'
                  AND a.shop_id IS NOT NULL
                  AND s.owner_id = :owner_id
                  AND a.status NOT IN (
                      'cancelled', 'rejected', 'no_show'
                  )
                LIMIT 1
            ");

                $stmt->execute([
                    'appointment_id' => $relatedId,
                    'customer_id' => $userId,
                    'owner_id' => $recipientId,
                ]);
            }

            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException(
                    'The appointment does not authorize contact with this provider.'
                );
            }

            $relatedAppointmentId = $relatedId;
        }

        // Reuse an existing, non-expired conversation for this exact
        // business relationship.
        $existing = $this->conversationRepository
            ->findExistingBusinessConversation(
                $type,
                $userId,
                $recipientId,
                $relatedOrderId,
                $relatedAppointmentId
            );

        if ($existing) {
            return [
                'conversation_id' => $existing['public_id'],
                'conversation_type' => $type,
                'created' => false,
            ];
        }

        $conversationId = $this->conversationRepository->create([
            'conversation_type' => $type,
            'participant_one_id' => $userId,
            'participant_two_id' => $recipientId,
            'related_order_id' => $relatedOrderId,
            'related_appointment_id' => $relatedAppointmentId,
            'created_by' => $userId,
        ]);

// Retrieve the created conversation to obtain its public UUID.
        $conversation = $this->conversationRepository->findById(
            $conversationId
        );

        if (!$conversation || empty($conversation['public_id'])) {
            throw new RuntimeException(
                'Could not retrieve the created conversation.'
            );
        }

        return [
            'conversation_id' => $conversation['public_id'],
            'conversation_type' => $type,
            'created' => true,
        ];
    }





    /**
     * Retrieve messages for a conversation participant.
     * Public UUIDs are returned instead of internal user and conversation IDs.
     */
    public function getMessages(
        string $conversationPublicId,
        int $userId,
        int $limit = 100,
        int $beforeId = 0
    ): array {
        $this->requireValidUserId($userId);

        if ($limit < 1 || $limit > 100 || $beforeId < 0) {
            throw new RuntimeException('Invalid pagination parameters.');
        }

        $conversation = $this->resolveConversationForParticipant(
            $conversationPublicId,
            $userId
        );

        $internalConversationId = (int) $conversation['id'];

        $messages = $this->messageRepository->findByConversationId(
            $internalConversationId,
            $limit,
            $beforeId
        );

        if (empty($messages)) {
            return [];
        }

        // Collect the internal sender IDs for UUID lookup.
        $senderIds = array_values(array_unique(array_map(
            'intval',
            array_column($messages, 'sender_id')
        )));

        $placeholders = [];
        $params = [];

        foreach ($senderIds as $index => $senderId) {
            $placeholder = ':sender_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $senderId;
        }

        $sql = '
        SELECT id, public_id
        FROM users
        WHERE id IN (' . implode(', ', $placeholders) . ')
    ';

        $stmt = $this->usersDb->prepare($sql);
        $stmt->execute($params);

        $publicIdsByInternalId = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
            $publicIdsByInternalId[(int) $user['id']] = $user['public_id'];
        }

        // Convert internal identifiers before returning the API response.
        foreach ($messages as &$message) {
            $internalSenderId = (int) $message['sender_id'];

            if (!isset($publicIdsByInternalId[$internalSenderId])) {
                throw new RuntimeException(
                    'Unable to resolve a message sender.'
                );
            }

            $message['sender_id'] = $publicIdsByInternalId[$internalSenderId];
            $message['conversation_id'] = $conversationPublicId;
        }

        unset($message);

        return $messages;
    }



    /**
     * Send a message to an existing conversation.
     * The authenticated user ID must be supplied by the controller.
     * Never accept the sender ID directly from frontend request data.
     */
    public function sendMessage(
        string $conversationPublicId,
        int $senderId,
        string $content
    ): array {
        $this->requireValidUserId($senderId);

        $conversation = $this->resolveConversationForParticipant(
            $conversationPublicId,
            $senderId
        );

        $conversationId = (int) $conversation['id'];

        $content = trim($content);

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

        $this->db->beginTransaction();

        try {
            // Locking the row prevents simultaneous first messages
            // from establishing different expiry timestamps.
            $conversation = $this->conversationRepository
                ->lockById($conversationId);

            if (!$conversation) {
                throw new RuntimeException(
                    'Conversation not found.'
                );
            }

            $this->assertParticipant($conversation, $senderId);
            $this->ensureConversationNotExpired($conversation);

            // Only the first message can initialize expiry.
            if (
                $conversation['conversation_type'] !== 'COMPLAINT'
                && $conversation['first_message_at'] === null
            ) {
                $this->conversationRepository
                    ->setFirstMessageExpiry($conversationId);

                // Reload the locked row to verify the timestamps.
                $conversation = $this->conversationRepository
                    ->lockById($conversationId);

                if (
                    !$conversation
                    || empty($conversation['first_message_at'])
                    || empty($conversation['expires_at'])
                ) {
                    throw new RuntimeException(
                        'Could not initialize conversation expiry.'
                    );
                }
            }

            // Recheck expiry before saving the message.
            $this->ensureConversationNotExpired($conversation);

            $messageId = $this->messageRepository->create(
                $conversationId,
                $senderId,
                $content
            );

            $this->conversationRepository->touch($conversationId);

            $this->db->commit();

            // Resolve the sender's public UUID for the API response.
            $senderStmt = $this->usersDb->prepare("
    SELECT public_id
    FROM users
    WHERE id = :id
    LIMIT 1
");

            $senderStmt->execute(['id' => $senderId]);
            $senderPublicId = $senderStmt->fetchColumn();

            if (!$senderPublicId) {
                throw new RuntimeException(
                    'Unable to resolve the message sender.'
                );
            }

            return [
                'id' => $messageId,
                'conversation_id' => $conversationPublicId,
                'sender_id' => $senderPublicId,
                'content' => $content,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'delivered_at' => null,
                'read_at' => null,
            ];

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    // Check whether a user is one of the two conversation participants.
    private function assertParticipant(
        array $conversation,
        int $userId
    ): void {
        $participantOne = (int) $conversation['participant_one_id'];
        $participantTwo = (int) $conversation['participant_two_id'];

        if (
            $userId !== $participantOne
            && $userId !== $participantTwo
        ) {
            throw new RuntimeException(
                'You are not allowed to access this conversation.'
            );
        }
    }

     //Reject access to expired non-complaint conversations.
    private function ensureConversationNotExpired(
        array $conversation
    ): void {
        if ($conversation['conversation_type'] === 'COMPLAINT') {
            return;
        }

        $expiresAt = $conversation['expires_at'] ?? null;

        // A conversation without a first message has no expiry yet.
        if ($expiresAt === null) {
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT UTC_TIMESTAMP() < :expires_at AS is_active'
        );

        $stmt->execute(['expires_at' => $expiresAt]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || (int) $result['is_active'] !== 1) {
            throw new RuntimeException(
                'This conversation has expired.'
            );
        }
    }


    /**
     * Resolve a public conversation UUID to its internal database ID.
     *
     * Access is granted only if the authenticated user participates
     * in the conversation.
     */
    private function resolveConversationForParticipant(
        string $publicId,
        int $userId
    ): array {
        if (
            !preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                $publicId
            )
        ) {
            throw new RuntimeException('Invalid conversation ID.');
        }

        $this->requireValidUserId($userId);

        $conversation = $this->conversationRepository
            ->findForParticipantByPublicId($publicId, $userId);

        if (!$conversation) {
            throw new RuntimeException(
                'Conversation not found or access denied.'
            );
        }

        $this->ensureConversationNotExpired($conversation);

        return $conversation;
    }


    private function requireValidUserId(int $userId): void
    {
        if ($userId <= 0) {
            throw new RuntimeException(
                'An authenticated user is required.'
            );
        }
    }


    /**
     * Create a complaint conversation between two distinct users.
     *
     * Role validation and permission checks must be performed by the
     * controller/service authorization layer before this method is called.
     */
    public function createComplaintConversation(
        int $participantOneId,
        int $participantTwoId,
        int $createdBy
    ): string {
        $this->requireValidUserId($participantOneId);
        $this->requireValidUserId($participantTwoId);
        $this->requireValidUserId($createdBy);

        if ($participantOneId === $participantTwoId) {
            throw new RuntimeException(
                'A complaint conversation requires two different users.'
            );
        }

        if (
            $createdBy !== $participantOneId
            && $createdBy !== $participantTwoId
        ) {
            throw new RuntimeException(
                'The creator must participate in the complaint conversation.'
            );
        }

        // Create the conversation using internal database IDs.
        $conversationId = $this->conversationRepository->create([
            'conversation_type' => 'COMPLAINT',
            'participant_one_id' => $participantOneId,
            'participant_two_id' => $participantTwoId,
            'created_by' => $createdBy,
        ]);

        // Resolve the public UUID for the API response.
        $conversation = $this->conversationRepository->findById(
            $conversationId
        );

        if (!$conversation || empty($conversation['public_id'])) {
            throw new RuntimeException(
                'Could not retrieve the created complaint conversation.'
            );
        }

        return $conversation['public_id'];
    }

    /**
     * Manually delete a complaint conversation.
     * The controller must first confirm that the authenticated user
     * has the ADMIN or FINANCE_MANAGER role.
     */
    public function deleteComplaintConversation(
        string $conversationPublicId,
        int $deletedBy
    ): void {
        $this->requireValidUserId($deletedBy);

        if (
            !preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                $conversationPublicId
            )
        ) {
            throw new RuntimeException('Invalid conversation ID.');
        }

        $conversation = $this->conversationRepository
            ->findByPublicId($conversationPublicId);

        if (!$conversation) {
            throw new RuntimeException('Conversation not found.');
        }

        $conversationId = (int) $conversation['id'];

        $this->db->beginTransaction();

        try {
            $conversation = $this->conversationRepository
                ->lockById($conversationId);

            if (!$conversation) {
                throw new RuntimeException(
                    'Conversation not found.'
                );
            }

            if ($conversation['conversation_type'] !== 'COMPLAINT') {
                throw new RuntimeException(
                    'Only complaint conversations can be manually deleted.'
                );
            }

            // Record the deletion independently of the conversation row.
            $audit = $this->db->prepare("
            INSERT INTO conversation_deletion_audit (
    original_conversation_id,
    deleted_by,
    deletion_source
) VALUES (
    :conversation_id,
    :deleted_by,
    'MANUAL'
)
        ");

            $audit->execute([
                'conversation_id' => $conversationId,
                'deleted_by' => $deletedBy,
            ]);

            // Delete messages first; the foreign key also uses CASCADE.
            $deleteMessages = $this->db->prepare("
            DELETE FROM messages
            WHERE conversation_id = :conversation_id
        ");

            $deleteMessages->execute([
                'conversation_id' => $conversationId,
            ]);

            // Permanently remove the conversation.
            $deleteConversation = $this->db->prepare("
            DELETE FROM conversations
            WHERE id = :conversation_id
        ");

            $deleteConversation->execute([
                'conversation_id' => $conversationId,
            ]);

            if ($deleteConversation->rowCount() !== 1) {
                throw new RuntimeException(
                    'The complaint conversation could not be deleted.'
                );
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }


    public function markMessagesAsDelivered(
        string $conversationPublicId,
        int $recipientId
    ): int {
        $this->requireValidUserId($recipientId);

        $conversation = $this->resolveConversationForParticipant(
            $conversationPublicId,
            $recipientId
        );

        $conversationId = (int) $conversation['id'];

        return $this->messageRepository->markAsDelivered(
            $conversationId,
            $recipientId
        );
    }

    public function markMessagesAsRead(
        string $conversationPublicId,
        int $recipientId
    ): int {
        $this->requireValidUserId($recipientId);

        $conversation = $this->resolveConversationForParticipant(
            $conversationPublicId,
            $recipientId
        );

        $conversationId = (int) $conversation['id'];

        return $this->messageRepository->markAsRead(
            $conversationId,
            $recipientId
        );
    }
}