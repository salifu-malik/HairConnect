<?php

namespace Api\Controllers\Auth;

use App\Repositories\PasswordResetRepository;
use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;
use App\Repositories\AuditLogRepository;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Helpers\RedisManager;
use App\Helpers\Logger;
use App\Helpers\LoginRiskAssessor;
use App\Services\MailService;
use App\Exceptions\AuthenticationException;
use App\Exceptions\EmailNotVerifiedException;
use App\Helpers\RequestBodyReader;
use App\Helpers\TurnstileVerifierInterface;
use App\Helpers\CloudflareTurnstileVerifier;
use App\Helpers\RefreshTokenCookieManager;
use App\Helpers\RefreshTokenCookieManagerInterface;

class AuthController
{
    private AuthService $authService;
    private LoginRiskAssessor $loginRiskAssessor;
    private TurnstileVerifierInterface $turnstileVerifier;

    private RequestBodyReader $requestBodyReader;

    private RefreshTokenCookieManagerInterface $refreshTokenCookieManager;

    /**
     * Dependencies can be injected for testing.
     *
     * When no dependencies are supplied, initialize the normal
     * production services.
     */
    public function __construct(
        ?AuthService $authService = null,
        ?LoginRiskAssessor $loginRiskAssessor = null,
        ?TurnstileVerifierInterface $turnstileVerifier = null,
        ?RequestBodyReader $requestBodyReader = null,
        ?RefreshTokenCookieManagerInterface $refreshTokenCookieManager = null
    ) {
        $redisManager = new RedisManager();

        $this->loginRiskAssessor = $loginRiskAssessor
            ?? new LoginRiskAssessor($redisManager);

        $this->authService = $authService
            ?? new AuthService(
                new UserRepository(),
                new SessionRepository(),
                new PasswordResetRepository(),
                new MailService(),
                $redisManager,
                new AuditLogService(
                    new AuditLogRepository()
                )
            );

        $this->turnstileVerifier = $turnstileVerifier
            ?? new CloudflareTurnstileVerifier();

        $this->requestBodyReader = $requestBodyReader
            ?? new RequestBodyReader();

        $this->refreshTokenCookieManager = $refreshTokenCookieManager
            ?? new RefreshTokenCookieManager();
    }



    private function setRefreshTokenCookie(string $refreshToken): void
    {
        $this->refreshTokenCookieManager->set($refreshToken);
    }

    private function clearRefreshTokenCookie(): void
    {
        $this->refreshTokenCookieManager->clear();
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

            $turnstileToken = $data['turnstile_token'] ?? '';

            if (
                !is_string($turnstileToken) ||
                !$this->turnstileVerifier->verify($turnstileToken)
            ) {
                http_response_code(403);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'Security verification failed. Please try again.'
                ]);

                return;
            }

            unset($data['turnstile_token']);

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
    public function login(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        /*
         * 1. Decode and validate the request body.
         */
        $data = $this->requestBodyReader->read();

        if (!is_array($data)) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid login request.',
            ]);

            return;
        }

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (
            !is_string($email) ||
            !is_string($password) ||
            trim($email) === '' ||
            $password === ''
        ) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => 'Email and password are required.',
            ]);

            return;
        }

        try {
            /*
             * 2. Assess login risk before password verification.
             *
             * The risk assessor and AuthService share the same
             * RedisManager instance through the constructor.
             */
            $risk = $this->loginRiskAssessor->assess(
                trim($email),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            );

            /*
             * 3. Reject high-risk requests before authentication.
             */
            if ($risk['level'] === 'high') {
                Logger::warning(
                    'High-risk login request rejected.',
                    [
                        'risk_level' => $risk['level'],
                        'reason' => $risk['reason'],
                    ],
                    'auth'
                );

                http_response_code(429);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'Too many requests. Please try again later.',
                ]);

                return;
            }

            /*
             * 4. Require Turnstile verification for medium-risk
             * requests only.
             */
            if ($risk['level'] === 'medium') {
                $turnstileToken = $data['turnstile_token'] ?? '';

                if (
                    !is_string($turnstileToken) ||
                    trim($turnstileToken) === ''
                ) {
                    Logger::warning(
                        'Medium-risk login rejected because the Turnstile token is missing.',
                        [
                            'risk_level' => $risk['level'],
                            'reason' => $risk['reason'],
                        ],
                        'auth'
                    );

                    http_response_code(403);

                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Security verification failed. Please try again.',
                    ]);

                    return;
                }

                if (!$this->turnstileVerifier->verify($turnstileToken)) {
                    Logger::warning(
                        'Medium-risk login rejected after Turnstile verification failed.',
                        [
                            'risk_level' => $risk['level'],
                            'reason' => $risk['reason'],
                        ],
                        'auth'
                    );

                    http_response_code(403);

                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Security verification failed. Please try again.',
                    ]);

                    return;
                }
            }

            /*
             * 5. Authenticate the user.
             *
             * AuthService handles password verification,
             * failed-attempt tracking, and email verification.
             */
            $result = $this->authService->login(
                trim($email),
                $password
            );

            /*
             * 6. Store the refresh token in an HttpOnly cookie.
             */
            $this->setRefreshTokenCookie(
                $result['refresh_token']
            );

            /*
             * Never return the refresh token in the JSON response.
             */
            unset($result['refresh_token']);

            /*
             * 7. Return the successful authentication response.
             */
            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $result,
            ]);

        } catch (AuthenticationException $e) {
            /*
             * Invalid credentials.
             */
            http_response_code(401);

            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid credentials.',
            ]);

        } catch (EmailNotVerifiedException $e) {
            /*
             * Password accepted, but email verification is required.
             */
            http_response_code(403);

            echo json_encode([
                'status' => 'error',
                'message' => 'Please verify your email before logging in.',
            ]);

        } catch (\RuntimeException $e) {
            /*
             * Infrastructure failure, such as Redis or Turnstile
             * being unavailable.
             *
             * Do not expose internal exception details to the client.
             */
            Logger::error(
                'Login security infrastructure failure.',
                [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
                'auth'
            );

            http_response_code(503);

            echo json_encode([
                'status' => 'error',
                'message' => 'Login security is temporarily unavailable. Please try again later.',
            ]);

        } catch (\Throwable $e) {
            /*
             * Unexpected application or server error.
             */
            Logger::error(
                'Unexpected login failure.',
                [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
                'auth'
            );

            http_response_code(500);

            echo json_encode([
                'status' => 'error',
                'message' => 'An unexpected server error occurred.',
            ]);
        }
    }


    /**
     * POST /api/auth/refresh
     */
    public function refresh(): void
    {
        header('Content-Type: application/json');

        try {
            // Read the refresh token from the HttpOnly cookie.
            $refreshToken = $_COOKIE['refresh_token'] ?? '';

            if (!is_string($refreshToken) || $refreshToken === '') {
                http_response_code(401);

                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid or expired refresh token.',
                ]);

                return;
            }

            $result = $this->authService->refresh($refreshToken);

            // Store the rotated refresh token in the HttpOnly cookie.
            $this->setRefreshTokenCookie($result['refresh_token']);

            // Never expose the refresh token in the JSON response.
            unset($result['refresh_token']);

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            // Do not log the refresh token or other credentials.
            \App\Helpers\Logger::error(
                'Refresh-token operation failed.',
                [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ],
                'auth'
            );

            // Unexpected errors should not be reported as authentication
            // failures because they may indicate a server-side problem.
            $statusCode = $e instanceof \App\Exceptions\AuthenticationException
                ? 401
                : 500;

            http_response_code($statusCode);

            echo json_encode([
                'status' => 'error',
                'message' => $statusCode === 401
                    ? 'Invalid or expired refresh token.'
                    : 'Unable to refresh your session. Please try again.',
            ]);
        }
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(): void
    {
        header('Content-Type: application/json');

        try {
            $refreshToken = $_COOKIE['refresh_token'] ?? '';

            if (is_string($refreshToken) && $refreshToken !== '') {
                $this->authService->logout($refreshToken);
            }

            $this->clearRefreshTokenCookie();

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Logged out',
            ]);
        } catch (\Throwable $e) {
            // Attempt to clear the browser cookie even if revocation fails.
            try {
                $this->clearRefreshTokenCookie();
            } catch (\Throwable $cookieException) {
                \App\Helpers\Logger::error(
                    'Failed to clear the refresh-token cookie during logout.',
                    [
                        'exception' => get_class($cookieException),
                        'message' => $cookieException->getMessage(),
                    ],
                    'auth'
                );
            }

            \App\Helpers\Logger::error(
                'Logout operation failed.',
                [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ],
                'auth'
            );

            http_response_code(500);

            echo json_encode([
                'status' => 'error',
                'message' => 'Unable to complete logout. Please try again.',
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