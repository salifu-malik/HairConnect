<?php

namespace Api\Controllers\Notification;

use Api\Middleware\AuthMiddleware;
use App\Repositories\NotificationRepository;

class NotificationController
{
    private NotificationRepository $notificationRepository;

    public function __construct()
    {
        $this->notificationRepository = new NotificationRepository();
    }

    /**
     * GET /api/notifications
     * Retrieve notifications for the authenticated user.
     */
    public function index(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $auth = AuthMiddleware::authenticate();
            $userId = (int) $auth['user']->id;

            $limit = filter_var(
                $_GET['limit'] ?? 20,
                FILTER_VALIDATE_INT
            );

            $offset = filter_var(
                $_GET['offset'] ?? 0,
                FILTER_VALIDATE_INT
            );

            if (
                $limit === false ||
                $limit < 1 ||
                $offset === false ||
                $offset < 0
            ) {
                http_response_code(400);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid pagination parameters.'
                ]);

                return;
            }

            $notifications = $this->notificationRepository
                ->getNotificationsByUser($userId, $limit, $offset);

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $notifications
            ]);
        } catch (\Throwable $e) {
            error_log('Notification retrieval error: ' . $e->getMessage());

            http_response_code(500);

            echo json_encode([
                'status' => 'error',
                'message' => 'Unable to retrieve notifications.'
            ]);
        }
    }

    /**
     * GET /api/notifications/unread-count
     * Retrieve the authenticated user's unread notification count.
     */
    public function unreadCount(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $auth = AuthMiddleware::authenticate();
            $userId = (int) $auth['user']->id;

            $count = $this->notificationRepository
                ->getUnreadCount($userId);

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'unread_count' => $count
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('Unread notification count error: ' . $e->getMessage());

            http_response_code(500);

            echo json_encode([
                'status' => 'error',
                'message' => 'Unable to retrieve unread notification count.'
            ]);
        }
    }

    /**
     * PATCH /api/notifications/read
     * Mark one of the authenticated user's notifications as read.
     */
    public function markAsRead(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $auth = AuthMiddleware::authenticate();
            $userId = (int) $auth['user']->id;

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            if (!is_array($data)) {
                http_response_code(400);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid request data.'
                ]);

                return;
            }

            $notificationId = filter_var(
                $data['notification_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($notificationId === false || $notificationId === null || $notificationId < 1) {
                http_response_code(400);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'A valid notification_id is required.'
                ]);

                return;
            }

            $updated = $this->notificationRepository
                ->markAsRead($notificationId, $userId);

            if (!$updated) {
                http_response_code(404);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'Notification not found or already read.'
                ]);

                return;
            }

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Notification marked as read.'
            ]);
        } catch (\Throwable $e) {
            error_log('Mark notification as read error: ' . $e->getMessage());

            http_response_code(500);

            echo json_encode([
                'status' => 'error',
                'message' => 'Unable to update notification.'
            ]);
        }
    }
}