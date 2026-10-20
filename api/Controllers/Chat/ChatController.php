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
     *
     * JSON body:
     * {
     *     "conversation_type": "HOME_SERVICE",
     *     "recipient_id": 23,
     *     "related_id": 146
     * }
     */
    public function startBusinessConversation(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $auth = AuthMiddleware::authenticate();
            $userId = (int) $auth['user']->id;

            $data = $this->readJsonBody();

            $type = $data['conversation_type'] ?? '';
            $recipientId = filter_var(
                $data['recipient_id'] ?? null,
                FILTER_VALIDATE_INT
            );
            $relatedId = filter_var(
                $data['related_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $allowedTypes = [
                'PRODUCT_ORDER',
                'HOME_SERVICE',
                'SHOP_SERVICE',
            ];

            if (
                !is_string($type)
                || !in_array($type, $allowedTypes, true)
                || $recipientId === false
                || $recipientId === null
                || $recipientId <= 0
                || $relatedId === false
                || $relatedId === null
                || $relatedId <= 0
            ) {
                $this->respond(400, [
                    'status' => 'error',
                    'message' => 'Valid conversation details are required.',
                ]);

                return;
            }

            $conversation = $this->chatService->startBusinessConversation(
                $userId,
                $type,
                (int) $recipientId,
                (int) $relatedId
            );

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
    public function getMessages(int $conversationId): void
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
                $conversationId,
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
    public function sendMessage(int $conversationId): void
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
                $conversationId,
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
     *     "recipient_id": 123
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

            $recipientId = filter_var(
                $data['recipient_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (
                $recipientId === false
                || $recipientId === null
                || $recipientId <= 0
                || $recipientId === $creatorId
            ) {
                $this->respond(400, [
                    'status' => 'error',
                    'message' => 'A valid recipient is required.',
                ]);

                return;
            }

            $recipient = $this->userRepository->findById($recipientId);

            if (!$recipient || $recipient->status !== 'active') {
                $this->respond(404, [
                    'status' => 'error',
                    'message' => 'Recipient not found or inactive.',
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
                    'id' => $conversationId,
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
    public function deleteComplaint(int $conversationId): void
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
                $conversationId,
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
    public function markMessagesAsDelivered(int $conversationId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $auth = AuthMiddleware::authenticate();
            $userId = (int) $auth['user']->id;

            $updated = $this->chatService->markMessagesAsDelivered(
                $conversationId,
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
    public function markMessagesAsRead(int $conversationId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $auth = AuthMiddleware::authenticate();
            $userId = (int) $auth['user']->id;

            $updated = $this->chatService->markMessagesAsRead(
                $conversationId,
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