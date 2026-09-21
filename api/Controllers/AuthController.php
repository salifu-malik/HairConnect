<?php

namespace Api\Controllers;

use App\Services\AuthService;
use App\Repositories\UserRepository;
use App\Repositories\SessionRepository;
use App\Repositories\PasswordResetRepository;
use App\Services\MailService;

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService(
            new UserRepository(),
            new SessionRepository(),
            new PasswordResetRepository(),
            new MailService()
        );
    }

    /**
     * POST /api/auth/register
     */
    public function register()
    {
        header('Content-Type: application/json');

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        try {

            $this->authService->register($data);

            http_response_code(201);

            echo json_encode([
                'status' => 'success',
                'message' => 'User registered'
            ]);

        } catch (\Exception $e) {

            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /api/auth/login
     */
    public function login()
    {
        header('Content-Type: application/json');

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        try {

            $result = $this->authService->login(
                $data['email'] ?? '',
                $data['password'] ?? ''
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $result
            ]);

        } catch (\Throwable $e) {

            http_response_code(500);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
                'type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * POST /api/auth/refresh
     */
    public function refresh()
    {
        header('Content-Type: application/json');

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        try {

            $result = $this->authService->refresh(
                $data['refresh_token'] ?? ''
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $result
            ]);

        } catch (\Exception $e) {

            http_response_code(401);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /api/auth/logout
     */
    public function logout()
    {
        header('Content-Type: application/json');

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        try {

            $this->authService->logout(
                $data['refresh_token'] ?? ''
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Logged out'
            ]);

        } catch (\Exception $e) {

            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /api/auth/password/forgot
     */
    public function forgotPassword()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        try {

            $email = trim(
                strtolower($data['email'] ?? '')
            );

            if (empty($email)) {
                throw new \Exception(
                    'Email is required.'
                );
            }

            /*
             * The verification code is generated and
             * sent by AuthService through PHPMailer.
             *
             * The actual code is never returned to
             * the frontend.
             */
            $this->authService->forgotPassword($email);

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' =>
                    'If an account exists with that email, a verification code has been sent.'
            ]);

        } catch (\Throwable $e) {

            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /api/auth/password/verify
     */
    public function verifyResetCode()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        try {

            $email = trim(
                strtolower($data['email'] ?? '')
            );

            $code = trim(
                $data['code'] ?? ''
            );

            if (empty($email) || empty($code)) {
                throw new \Exception(
                    'Email and verification code are required.'
                );
            }

            $resetToken = $this->authService
                ->verifyResetCode(
                    $email,
                    $code
                );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Verification successful.',
                'data' => [
                    'reset_token' => $resetToken
                ]
            ]);

        } catch (\Throwable $e) {

            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /api/auth/password/reset
     */
    public function resetPassword()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        try {

            $resetToken = trim(
                $data['reset_token'] ?? ''
            );

            $password = $data['password'] ?? '';

            $confirmPassword =
                $data['confirm_password'] ?? '';

            if (
                empty($resetToken) ||
                empty($password) ||
                empty($confirmPassword)
            ) {
                throw new \Exception(
                    'Reset token, password and confirmation password are required.'
                );
            }

            $this->authService->resetPassword(
                $resetToken,
                $password,
                $confirmPassword
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' =>
                    'Password has been reset successfully.'
            ]);

        } catch (\Throwable $e) {

            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}