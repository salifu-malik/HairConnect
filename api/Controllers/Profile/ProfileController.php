<?php


use App\Helpers\JwtHelper;
use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;

class ProfileController
{
    private UserRepository $userRepository;
    private SessionRepository $sessionRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->sessionRepository = new SessionRepository();
    }

    /**
     * Extract and validate the authenticated user's ID.
     */
    private function getAuthenticatedUserId(): ?int
    {
        $authorizationHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (
            $authorizationHeader === ''
            && function_exists('getallheaders')
        ) {
            $headers = getallheaders();

            $authorizationHeader =
                $headers['Authorization']
                ?? $headers['authorization']
                ?? '';
        }

        if ($authorizationHeader === '') {
            return null;
        }

        if (!preg_match(
            '/^Bearer\s+(.+)$/i',
            trim($authorizationHeader),
            $matches
        )) {
            return null;
        }

        $token = trim($matches[1]);

        $payload = JwtHelper::decode($token);

        if ($payload === null) {
            return null;
        }

        $userId = $payload['user_id'] ?? null;

        if (
            $userId === null ||
            !is_numeric($userId)
        ) {
            return null;
        }

        return (int) $userId;
    }

    /**
     * GET /api/profile
     */
    public function getProfile(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $userId = $this->getAuthenticatedUserId();

            if ($userId === null) {
                http_response_code(401);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid or missing authorization token.'
                ]);

                return;
            }

            $user = $this->userRepository->findById($userId);

            if ($user === null) {
                http_response_code(404);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'User not found.'
                ]);

                return;
            }

            $roles = $this->userRepository->getRoles($user->id);

            $user->roles = $roles;

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $user
            ]);

        } catch (\Throwable $e) {
            http_response_code(500);

            echo json_encode([
                'status' => 'error',
                'message' => 'Unable to retrieve profile.'
            ]);
        }
    }

    /**
     * PUT /api/profile
     */
    public function updateProfile(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $userId = $this->getAuthenticatedUserId();

            if ($userId === null) {
                http_response_code(401);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid or missing authorization token.'
                ]);

                return;
            }

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

            $firstName = trim($data['firstname'] ?? '');
            $lastName = trim($data['lastname'] ?? '');
            $phone = trim($data['phone'] ?? '');

            if (strlen($firstName) < 2) {
                http_response_code(400);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'First name must be at least 2 characters.'
                ]);

                return;
            }

            if (strlen($lastName) < 2) {
                http_response_code(400);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'Last name must be at least 2 characters.'
                ]);

                return;
            }

            if ($phone === '') {
                http_response_code(400);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'Phone number is required.'
                ]);

                return;
            }

            $this->userRepository->updateProfile(
                $userId,
                $firstName,
                $lastName,
                $phone
            );

            $user = $this->userRepository->findById($userId);

            if ($user === null) {
                http_response_code(404);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'User not found.'
                ]);

                return;
            }

            $user->roles = $this->userRepository->getRoles($userId);

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Profile updated successfully.',
                'data' => $user
            ]);

        } catch (\Throwable $e) {
            http_response_code(500);

            echo json_encode([
                'status' => 'error',
                'message' => 'Unable to update profile.'
            ]);
        }
    }

    /**
     * POST /api/profile/change-password
     */
    public function changePassword(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $userId = $this->getAuthenticatedUserId();

            if ($userId === null) {
                http_response_code(401);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid or missing authorization token.'
                ]);

                return;
            }

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

            $currentPassword = $data['currentPassword'] ?? '';
            $newPassword = $data['newPassword'] ?? '';

            if ($currentPassword === '') {
                http_response_code(400);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'Current password is required.'
                ]);

                return;
            }

            if ($newPassword === '') {
                http_response_code(400);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'New password is required.'
                ]);

                return;
            }

            if (strlen($newPassword) < 8) {
                http_response_code(400);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'New password must be at least 8 characters.'
                ]);

                return;
            }

            if ($currentPassword === $newPassword) {
                http_response_code(400);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'New password must be different from the current password.'
                ]);

                return;
            }

            $passwordHash = $this->userRepository
                ->getPasswordHashById($userId);

            if ($passwordHash === null) {
                http_response_code(404);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'User not found.'
                ]);

                return;
            }

            if (!password_verify($currentPassword, $passwordHash)) {
                http_response_code(401);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'Current password is incorrect.'
                ]);

                return;
            }

            $newPasswordHash = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            $this->userRepository->updatePassword(
                $userId,
                $newPasswordHash
            );

            // Invalidate all existing refresh-token sessions.
            $this->sessionRepository->deleteAllForUser($userId);

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Password updated successfully. Please log in again.'
            ]);

        } catch (\Throwable $e) {
            http_response_code(500);

            echo json_encode([
                'status' => 'error',
                'message' => 'Unable to update profile.',
                'debug' => $e->getMessage()
            ]);
        }
    }
}