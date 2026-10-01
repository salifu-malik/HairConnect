<?php

namespace Api\Controllers\Auth;

use App\Repositories\PasswordResetRepository;
use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;
use App\Repositories\AuditLogRepository;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Helpers\RedisManager;
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
            new MailService(),
            new RedisManager(),
            new AuditLogService(
                new AuditLogRepository()
            )
        );
    }

    private function setRefreshTokenCookie(string $refreshToken): void
    {
        $secure = getenv('COOKIE_SECURE') === 'true';

        setcookie('refresh_token', $refreshToken, [
            'expires' => time() + (7 * 24 * 60 * 60),
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => getenv('COOKIE_SAMESITE') ?: 'Lax',
        ]);
    }



    private function clearRefreshTokenCookie(): void
    {
        $secure = getenv('COOKIE_SECURE') === 'true';

        setcookie('refresh_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => getenv('COOKIE_SAMESITE') ?: 'Lax',
        ]);
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
     * POST /api/auth/verify-email
     */
    public function verifyEmail()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        try {

            $token = trim(
                $data['token'] ?? ''
            );

            if (empty($token)) {
                throw new \Exception(
                    'Verification token is required.'
                );
            }

            $this->authService->verifyEmail($token);

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Email verified successfully.'
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
     * POST /api/auth/resend-verification
     */
    public function resendVerification()
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

            $this->authService->resendVerificationEmail($email);

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' =>
                    'If the account exists and has not been verified, a new verification email has been sent.'
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

            /*
             * Store the refresh token in an HttpOnly cookie.
             */
            $this->setRefreshTokenCookie(
                $result['refresh_token']
            );

            /*
             * Never send the refresh token
             * back to the frontend in JSON.
             */
            unset($result['refresh_token']);

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $result
            ]);

        } catch (\Throwable $e) {

            http_response_code(401);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }


    /**
     * POST /api/auth/refresh
     */
    public function refresh()
    {
        header('Content-Type: application/json');

        try {

            /*
             * Read the refresh token from the HttpOnly cookie.
             */
            $refreshToken = $_COOKIE['refresh_token'] ?? '';

            if (empty($refreshToken)) {
                throw new \Exception(
                    'Refresh token is missing.'
                );
            }

            $result = $this->authService->refresh(
                $refreshToken
            );

            /*
             * The refresh token is rotated by AuthService.
             * Store the new token in the cookie.
             */
            $this->setRefreshTokenCookie(
                $result['refresh_token']
            );

            /*
             * Never expose the refresh token
             * to the frontend.
             */
            unset($result['refresh_token']);

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $result
            ]);

        } catch (\Throwable $e) {

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

        try {

            /*
             * Read refresh token from HttpOnly cookie.
             */
            $refreshToken = $_COOKIE['refresh_token'] ?? '';

            if (!empty($refreshToken)) {

                $this->authService->logout(
                    $refreshToken
                );
            }

            /*
             * Clear the browser cookie.
             */
            $this->clearRefreshTokenCookie();

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Logged out'
            ]);

        } catch (\Throwable $e) {

            /*
             * Even if server-side revocation fails,
             * attempt to remove the browser cookie.
             */
            $this->clearRefreshTokenCookie();

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