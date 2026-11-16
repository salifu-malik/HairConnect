<?php

namespace Api\Controllers\Chat;

use Api\Middleware\AuthMiddleware;
use App\Repositories\UserRepository;
use App\Services\ChatService;
use RuntimeException;
use Throwable;

class ChatController
{
    private ChatService $chatService;
    private UserRepository $userRepository;

    public function __construct(
        ?ChatService $chatService = null,
        ?UserRepository $userRepository = null
    ) {
        $this->chatService = $chatService ?? new ChatService();
        $this->userRepository = $userRepository ?? new UserRepository();
    }

    /**
     * GET /api/chat/contacts
     *
     * Return contacts the authenticated user is eligible to message.
     */
    public function getContacts(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $auth = AuthMiddleware::authenticate();
            $userId = (int) $auth['user']->id;

            $contacts = $this->chatService->getContacts($userId);

            $this->respond(200, [
                'status' => 'success',
                'data' => $contacts,
            ]);
        } catch (Throwable $e) {
            $this->handleError(
                $e,
                'Unable to retrieve contacts.'
            );
        }
    }

    /**
     * POST /api/chat/conversations
     * JSON body:
     * {
     *     "conversation_type": "HOME_SERVICE",
     *     "recipient_id": "550e8400-e29b-41d4-a716-446655440000",
     *     "related_id": 146
     * }
     * recipient_id is the recipient's public UUID.
     * related_id is currently the internal order or appointment ID.
     */
    public function startBusinessConversation(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            // Authenticate the current user.
            $auth = AuthMiddleware::authenticate();
            $userId = (int) $auth['user']->id;

            // Read and validate the request body.
            $data = $this->readJsonBody();

            $type = $data['conversation_type'] ?? '';
            $recipientPublicId = $data['recipient_id'] ?? null;

            $relatedId = filter_var(
                $data['related_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            // Supported business conversation types.
            $allowedTypes = [
                'PRODUCT_ORDER',
                'HOME_SERVICE',
                'SHOP_SERVICE',
            ];

            // Validate the conversation type.
            if (
                !is_string($type)
                || !in_array($type, $allowedTypes, true)
            ) {
                $this->respond(400, [
                    'status' => 'error',
                    'message' => 'Invalid conversation type.',
                ]);

                return;
            }

            // Validate the recipient's public UUID.
            if (
                !is_string($recipientPublicId)
                || !preg_match(
                    '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                    $recipientPublicId
                )
            ) {
                $this->respond(400, [
                    'status' => 'error',
                    'message' => 'A valid recipient ID is required.',
                ]);

                return;
            }

            // Validate the related order or appointment ID.
            if (
                $relatedId === false
                || $relatedId === null
                || $relatedId <= 0
            ) {
                $this->respond(400, [
                    'status' => 'error',
                    'message' => 'A valid order or appointment ID is required.',
                ]);

                return;
            }

            // Resolve the public UUID to the internal user record.
            $recipient = $this->userRepository->findByPublicId(
                $recipientPublicId
            );

            if (
                !$recipient
                || $recipient->status !== 'active'
            ) {
                $this->respond(404, [
                    'status' => 'error',
                    'message' => 'Recipient not found or inactive.',
                ]);

                return;
            }

            // Use the internal numeric ID only inside the backend.
            $recipientId = $recipient->id;

            // Prevent users from messaging themselves.
            if ($userId === $recipientId) {
                $this->respond(400, [
                    'status' => 'error',
                    'message' => 'You cannot start a conversation with yourself.',
                ]);

                return;
            }

            // The service verifies the user's role and the relationship
            // between the customer, recipient, order, or appointment.
            $conversation = $this->chatService->startBusinessConversation(
                $userId,
                $type,
                $recipientId,
                (int) $relatedId
            );

            // Return the result.
            $this->respond(
                $conversation['created'] ? 201 : 200,
                [
                    'status' => 'success',
                    'message' => $conversation['created']
                        ? 'Conversation created.'
                        : 'Existing conversation retrieved.',
                    'data' => $conversation,
                ]
            );
        } catch (Throwable $e) {
            $this->handleError(
                $e,
                'Unable to start business conversation.'
            );
        }
    }



    /**
     * GET /api/chat/chats
     */
    public function getChats(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $auth = AuthMiddleware::authenticate();
            $userId = (int) $auth['user']->id;

            $chats = $this->chatService->getConversations($userId);

            $this->respond(200, [
                'status' => 'success',
                'data' => $chats,
            ]);
        } catch (Throwable $e) {
            $this->handleError($e, 'Unable to retrieve conversations.');
        }
    }

    /**
     * GET /api/chat/messages/{conversationId}
     *
     * Optional query parameters:
     *   limit=100
     *   before_id=123
     */
    public function getMessages(string $conversationPublicId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $auth = AuthMiddleware::authenticate();
            $userId = (int) $auth['user']->id;

            $limit = filter_var(
                $_GET['limit'] ?? 100,
                FILTER_VALIDATE_INT
            );

            $beforeId = filter_var(
                $_GET['before_id'] ?? 0,
                FILTER_VALIDATE_INT
            );

            if (
                $limit === false
                || $beforeId === false
                || $beforeId < 0
            ) {
                $this->respond(400, [
                    'status' => 'error',
                    'message' => 'Invalid pagination parameters.',
                ]);

                return;
            }

            $messages = $this->chatService->getMessages(
                $conversationPublicId,
                $userId,
                $limit,
                $beforeId
            );

            $this->respond(200, [
                'status' => 'success',
                'data' => $messages,
            ]);
        } catch (Throwable $e) {
            $this->handleError($e, 'Unable to retrieve messages.');
        }
    }

    /**
     * POST /api/chat/messages/{conversationId}
     *
     * JSON body:
     * {
     *     "content": "Hello"
     * }
     */
    public function sendMessage(string $conversationPublicId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $auth = AuthMiddleware::authenticate();
            $userId = (int) $auth['user']->id;

            $data = $this->readJsonBody();

            if (!isset($data['content']) || !is_string($data['content'])) {
                $this->respond(400, [
                    'status' => 'error',
                    'message' => 'Message content is required.',
                ]);

                return;
            }

            $message = $this->chatService->sendMessage(
                $conversationPublicId,
                $userId,
                $data['content']
            );

            $this->respond(201, [
                'status' => 'success',
                'data' => $message,
            ]);
        } catch (Throwable $e) {
            $this->handleError($e, 'Unable to send message.');
        }
    }

    /**
     * POST /api/chat/complaints
     *
     * JSON body:
     * {
     *     "recipient_id": "recipient-public-uuid"
     * }
     *
     * A non-staff user must contact an administrator or
     * Finance Manager. Staff may initiate a complaint with a user.
     */
    public function createComplaint(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $auth = AuthMiddleware::authenticate();

            $creatorId = (int) $auth['user']->id;
            $creatorRoles = $this->userRepository->getRoles($creatorId);

            $data = $this->readJsonBody();

            $recipientPublicId = $data['recipient_id'] ?? null;

            if (
                !is_string($recipientPublicId)
                || !preg_match(
                    '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                    $recipientPublicId
                )
            ) {
                $this->respond(400, [
                    'status' => 'error',
                    'message' => 'A valid recipient UUID is required.',
                ]);

                return;
            }

            $recipient = $this->userRepository->findByPublicId(
                $recipientPublicId
            );

            if (!$recipient || $recipient->status !== 'active') {
                $this->respond(404, [
                    'status' => 'error',
                    'message' => 'Recipient not found or inactive.',
                ]);

                return;
            }

// Use the internal numeric ID only inside the backend.
            $recipientId = (int) $recipient->id;

            if ($recipientId === $creatorId) {
                $this->respond(400, [
                    'status' => 'error',
                    'message' => 'You cannot create a complaint conversation with yourself.',
                ]);

                return;
            }

            $recipientRoles = $this->userRepository->getRoles($recipientId);

            $staffRoles = ['ADMIN', 'FINANCE_MANAGER'];

            $creatorIsStaff = count(
                    array_intersect($staffRoles, $creatorRoles)
                ) > 0;

            $recipientIsStaff = count(
                    array_intersect($staffRoles, $recipientRoles)
                ) > 0;

            if (!$creatorIsStaff && !$recipientIsStaff) {
                $this->respond(403, [
                    'status' => 'error',
                    'message' => 'Complaints must involve an administrator or Finance Manager.',
                ]);

                return;
            }

            $conversationId = $this->chatService
                ->createComplaintConversation(
                    $creatorId,
                    $recipientId,
                    $creatorId
                );

            $this->respond(201, [
                'status' => 'success',
                'message' => 'Complaint conversation created.',
                'data' => [
                    'conversation_id' => $conversationId,
                    'conversation_type' => 'COMPLAINT',
                ],
            ]);
        } catch (Throwable $e) {
            $this->handleError($e, 'Unable to create complaint conversation.');
        }
    }

    /**
     * DELETE /api/chat/chats/{conversationId}
     *
     * Only ADMIN and FINANCE_MANAGER may use this endpoint.
     * Only complaint conversations may be deleted.
     */
    public function deleteComplaint(string $conversationPublicId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $auth = AuthMiddleware::authenticate();

            $userId = (int) $auth['user']->id;
            $roles = $this->userRepository->getRoles($userId);

            if (
                !in_array('ADMIN', $roles, true)
                && !in_array('FINANCE_MANAGER', $roles, true)
            ) {
                $this->respond(403, [
                    'status' => 'error',
                    'message' => 'You are not authorized to delete complaint conversations.',
                ]);

                return;
            }

            $this->chatService->deleteComplaintConversation(
                $conversationPublicId,
                $userId
            );

            $this->respond(200, [
                'status' => 'success',
                'message' => 'Complaint conversation deleted.',
            ]);
        } catch (Throwable $e) {
            $this->handleError($e, 'Unable to delete complaint conversation.');
        }
    }

    private function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);

        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON request body.');
        }

        return $data;
    }

    private function respond(int $status, array $payload): void
    {
        http_response_code($status);

        echo json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    private function handleError(
        Throwable $e,
        string $genericMessage
    ): void {
        // Do not log message contents or authentication tokens.
        error_log(
            'ChatController: ' . get_class($e) . ': ' . $e->getMessage()
        );

        $message = $e->getMessage();

        if ($message === 'Invalid JSON request body.') {
            $this->respond(400, [
                'status' => 'error',
                'message' => $message,
            ]);

            return;
        }

        if (
            str_contains($message, 'not allowed')
            || str_contains($message, 'access denied')
        ) {
            $this->respond(403, [
                'status' => 'error',
                'message' => 'You are not authorized to access this conversation.',
            ]);

            return;
        }

        if (
            str_contains($message, 'not found')
            || str_contains($message, 'expired')
        ) {
            $this->respond(404, [
                'status' => 'error',
                'message' => $message,
            ]);

            return;
        }

        if (
            str_contains($message, 'required')
            || str_contains($message, 'Invalid')
            || str_contains($message, 'cannot be empty')
            || str_contains($message, 'exceed')
        ) {
            $this->respond(400, [
                'status' => 'error',
                'message' => $message,
            ]);

            return;
        }

        $this->respond(500, [
            'status' => 'error',
            'message' => $genericMessage,
        ]);
    }

    /**
     * POST /api/chat/messages/{conversationId}/delivered
     */
    public function markMessagesAsDelivered(string $conversationPublicId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $auth = AuthMiddleware::authenticate();
            $userId = (int) $auth['user']->id;

            $updated = $this->chatService->markMessagesAsDelivered(
                $conversationPublicId,
                $userId
            );

            $this->respond(200, [
                'status' => 'success',
                'message' => 'Messages marked as delivered.',
                'data' => [
                    'updated_count' => $updated,
                ],
            ]);
        } catch (Throwable $e) {
            $this->handleError($e, 'Unable to mark messages as delivered.');
        }
    }

    /**
     * POST /api/chat/messages/{conversationId}/read
     */
    public function markMessagesAsRead(string $conversationPublicId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $auth = AuthMiddleware::authenticate();
            $userId = (int) $auth['user']->id;

            $updated = $this->chatService->markMessagesAsRead(
                $conversationPublicId,
                $userId
            );

            $this->respond(200, [
                'status' => 'success',
                'message' => 'Messages marked as read.',
                'data' => [
                    'updated_count' => $updated,
                ],
            ]);
        } catch (Throwable $e) {
            $this->handleError($e, 'Unable to mark messages as read.');
        }
    }

}