<?php


namespace App\Services;

use App\Repositories\UserRepository;
use App\Repositories\SessionRepository;
use App\Repositories\PasswordResetRepository;
use App\Validators\AuthValidator;
use App\Helpers\JwtHelper;
use App\Helpers\RedisManager;
use Exception;
use App\Repositories\AuditLogRepository;
use App\Services\AuditLogService;
use App\Services\MailService;


class AuthService
{
    private UserRepository $userRepository;
    private SessionRepository $sessionRepository;
    private PasswordResetRepository $passwordResetRepository;
    private RedisManager $redisManager;
    private MailService $mailService;
    private AuditLogService $auditLogService;

    public function __construct(UserRepository $userRepository, SessionRepository $sessionRepository,  PasswordResetRepository $passwordResetRepository, MailService $mailService, RedisManager $redisManager, AuditLogService $auditLogService)
    {
        $this->userRepository = $userRepository;
        $this->sessionRepository = $sessionRepository;
        $this->passwordResetRepository = $passwordResetRepository;
        $this->mailService = $mailService;
        $this->redisManager = $redisManager;
        $this->auditLogService = $auditLogService;
    }

    public function register(array $data): int
    {
        AuthValidator::validateRegistration($data);

        $email = trim(strtolower($data['email']));

        if ($this->userRepository->findByEmail($email)) {
            throw new Exception("Email already exists.");
        }

        // Normalize email
        $data['email'] = $email;

        // Create user
        $userId = $this->userRepository->create($data);

        // Assign selected role
        $this->userRepository->assignRole(
            $userId,
            $data['role']
        );

        /*
         * Generate a cryptographically secure verification token.
         *
         * The raw token is sent to the user's email.
         * Only its SHA-256 hash is stored in Redis.
         */
        $token = bin2hex(random_bytes(32));

        $tokenHash = hash('sha256', $token);

        /*
         * Store verification information in Redis.
         *
         * Token lifetime: 15 minutes.
         */
        $tokenKey = "email_verification:token:{$tokenHash}";

        $this->redisManager->set(
            $tokenKey,
            [
                'user_id' => $userId,
                'email' => $email,
            ],
            15 * 60
        );

        /*
         * Keep track of the current token for this user.
         * This allows a future resend operation to invalidate
         * the previous token.
         */
        $currentTokenKey = "email_verification:current:{$userId}";

        $this->redisManager->set(
            $currentTokenKey,
            [
                'token_hash' => $tokenHash,
            ],
            15 * 60
        );

        /*
         * Count verification emails.
         *
         * Maximum: 3 emails per 15 minutes.
         */
        $emailKey = hash('sha256', $email);

        $sendCountKey =
            "email_verification:send:15m:{$emailKey}";

        $sendCount = $this->redisManager->increment(
            $sendCountKey,
            15 * 60
        );

        if ($sendCount > 3) {
            /*
             * The account has already been created, but we should
             * not leave the newly generated verification token
             * active if the rate limit has been exceeded.
             */
            $this->redisManager->delete($tokenKey);
            $this->redisManager->delete($currentTokenKey);

            throw new Exception(
                "Too many verification emails requested. Please try again later."
            );
        }

        /*
         * Build frontend verification URL.
         */
        $frontendUrl =
            getenv('FRONTEND_URL')
                ?: 'http://localhost:5173';

        $verificationUrl =
            rtrim($frontendUrl, '/') .
            '/verify-email?token=' .
            urlencode($token);

        /*
         * Send verification email.
         */
        $this->mailService->sendEmailVerification(
            $email,
            $verificationUrl
        );

        return $userId;
    }




    public function resendVerificationEmail(string $email): void
    {
        $email = trim(strtolower($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address.');
        }

        $user = $this->userRepository->findByEmail($email);

        /*
         * Don't reveal whether an account exists.
         */
        if (!$user) {
            return;
        }

        /*
         * Already verified.
         */
        if ($user->emailVerifiedAt !== null) {
            return;
        }

        $emailKey = hash('sha256', $email);

        $sendCountKey =
            "email_verification:send:15m:{$emailKey}";

        $sendCount = $this->redisManager->increment(
            $sendCountKey,
            15 * 60
        );

        if ($sendCount > 3) {
            throw new Exception(
                "Too many verification emails requested. Please try again later."
            );
        }

        /*
         * Invalidate the previous token.
         */
        $currentTokenKey =
            "email_verification:current:{$user->id}";

        $currentToken = $this->redisManager->get(
            $currentTokenKey
        );

        if (
            is_array($currentToken) &&
            !empty($currentToken['token_hash'])
        ) {
            $oldTokenKey =
                "email_verification:token:" .
                $currentToken['token_hash'];

            $this->redisManager->delete($oldTokenKey);
        }

        /*
         * Generate new token.
         */
        $token = bin2hex(random_bytes(32));

        $tokenHash = hash('sha256', $token);

        $tokenKey =
            "email_verification:token:{$tokenHash}";

        $this->redisManager->set(
            $tokenKey,
            [
                'user_id' => $user->id,
                'email' => $email,
            ],
            15 * 60
        );

        $this->redisManager->set(
            $currentTokenKey,
            [
                'token_hash' => $tokenHash,
            ],
            15 * 60
        );

        $frontendUrl =
            getenv('FRONTEND_URL')
                ?: 'http://localhost:5173';

        $verificationUrl =
            rtrim($frontendUrl, '/') .
            '/verify-email?token=' .
            urlencode($token);

        $this->mailService->sendEmailVerification(
            $email,
            $verificationUrl
        );
    }


    /**
     * Verify a user's email address using the
     * token supplied from the verification link.
     */
    public function verifyEmail(string $token): void
    {
        $token = trim($token);

        if ($token === '') {
            throw new Exception(
                'Verification token is required.'
            );
        }

        /*
         * Hash the token supplied by the client.
         *
         * The raw token is never stored in Redis.
         */
        $tokenHash = hash(
            'sha256',
            $token
        );

        $tokenKey =
            "email_verification:token:{$tokenHash}";

        /*
         * Retrieve the verification record from Redis.
         */
        $verificationData = $this->redisManager->get(
            $tokenKey
        );

        /*
         * Redis returns null when the token does not exist
         * or has expired.
         */
        if (
            !is_array($verificationData) ||
            empty($verificationData['user_id'])
        ) {
            throw new Exception(
                'Invalid or expired verification link.'
            );
        }

        $userId = (int) $verificationData['user_id'];

        /*
         * Retrieve the user from the database.
         */
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            /*
             * Remove the invalid verification record.
             */
            $this->redisManager->delete($tokenKey);

            throw new Exception(
                'Invalid verification request.'
            );
        }

        /*
         * If the email has already been verified,
         * invalidate this token and stop.
         */
        if ($user->emailVerifiedAt !== null) {

            $this->redisManager->delete($tokenKey);

            $this->redisManager->delete(
                "email_verification:current:{$userId}"
            );

            throw new Exception(
                'This email address has already been verified.'
            );
        }

        /*
         * Mark the user's email as verified in MySQL.
         */
        $this->userRepository->markEmailAsVerified(
            $userId
        );

        /*
         * The verification token is now consumed.
         */
        $this->redisManager->delete($tokenKey);

        /*
         * Remove the current-token pointer.
         */
        $this->redisManager->delete(
            "email_verification:current:{$userId}"
        );
    }



    public function login(string $email, string $password)
    {
        $email = trim(strtolower($email));

        $user = $this->userRepository->findByEmail($email);

        if (
            !$user ||
            !password_verify(
                $password,
                $user->getPasswordHash()
            )
        ) {
            $this->auditLogService->log([
                'user_id' => $user?->id,
                'user_email' => $email,
                'user_role' => null,
                'action' => 'LOGIN_FAILED',
                'module' => 'AUTH',
                'description' => 'Login failed due to invalid credentials.',
                'metadata' => [
                    'reason' => 'invalid_credentials',
                ],
            ]);

            throw new Exception("Invalid credentials.");
        }
        if ($user->emailVerifiedAt === null) {
            throw new Exception(
                "Please verify your email before logging in."
            );
        }

        $roles = $this->userRepository->getRoles($user->id);

        $user->roles = $roles;

        $this->auditLogService->auth(
            'LOGIN_SUCCESS',
            $user,
            'User logged in successfully.'
        );

        $accessToken = JwtHelper::encode([
            'user_id' => $user->id,
            'email' => $user->email,
            'roles' => $roles
        ], 3600);

        $refreshToken = bin2hex(random_bytes(64));

        $expiresAt = date(
            'Y-m-d H:i:s',
            time() + (7 * 24 * 3600)
        );

        $this->sessionRepository->save(
            $user->id,
            $refreshToken,
            $expiresAt
        );

        return [
            'user' => $user,
            'token' => $accessToken,
            'refresh_token' => $refreshToken
        ];
    }

    public function refresh(string $refreshToken)
    {
        $session = $this->sessionRepository->findByToken($refreshToken);

        /*
         * Validate the refresh-token session.
         */
        if (
            !$session ||
            $session['expires_at'] < date('Y-m-d H:i:s')
        ) {
            throw new Exception("Invalid or expired refresh token.");
        }

        /*
         * Retrieve the user associated with the refresh-token session.
         */
        $user = $this->userRepository->findById(
            (int) $session['user_id']
        );

        if (!$user) {
            throw new Exception("User not found.");
        }

        /*
         * Retrieve the user's current roles.
         *
         * This ensures that newly changed roles are reflected
         * whenever a new access token is issued.
         */
        $roles = $this->userRepository->getRoles($user->id);
        $user->roles = $roles;

        /*
         * Generate a new access token.
         */
        $accessToken = JwtHelper::encode([
            'user_id' => $user->id,
            'email' => $user->email,
            'roles' => $roles
        ], 3600);

        /*
         * Record the successful token refresh.
         *
         * Never store the access token or refresh token
         * in the audit log.
         */
        $this->auditLogService->auth(
            'TOKEN_REFRESH',
            $user,
            'Access token refreshed successfully.'
        );

        /*
         * Rotate the refresh token.
         *
         * The old refresh token becomes invalid immediately.
         */
        $this->sessionRepository->deleteByToken($refreshToken);

        /*
         * Generate a new cryptographically secure refresh token.
         */
        $newRefreshToken = bin2hex(
            random_bytes(64)
        );

        /*
         * Set the new refresh-token expiration.
         */
        $expiresAt = date(
            'Y-m-d H:i:s',
            time() + (7 * 24 * 3600)
        );

        /*
         * Store the new refresh-token session.
         */
        $this->sessionRepository->save(
            $user->id,
            $newRefreshToken,
            $expiresAt
        );

        /*
         * Return the new tokens.
         *
         * AuthController will place the refresh token
         * into the HttpOnly cookie and remove it from
         * the JSON response.
         */
        return [
            'token' => $accessToken,
            'refresh_token' => $newRefreshToken
        ];
    }

    public function logout(string $refreshToken)
    {
        $session = $this->sessionRepository->findByToken($refreshToken);

        if ($session) {
            $user = $this->userRepository->findById(
                (int) $session['user_id']
            );

            if ($user) {
                $roles = $this->userRepository->getRoles($user->id);
                $user->roles = $roles;

                $this->auditLogService->auth(
                    'LOGOUT',
                    $user,
                    'User logged out successfully.'
                );
            }
        }

        $this->sessionRepository->deleteByToken($refreshToken);
    }

    public function forgotPassword(string $email): ?string
    {
        $email = trim(strtolower($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address.");
        }

        $user = $this->userRepository->findByEmail($email);

        /*
         * Do not reveal whether an account exists.
         */
        if (!$user) {
            return null;
        }

        /*
         * Enforce the 5-minute resend restriction.
         */
        $latestRequest = $this->passwordResetRepository
            ->findLatestByEmail($email);

        if ($latestRequest) {
            $lastSentAt = strtotime($latestRequest['last_sent_at']);
            $nextAllowedTime = $lastSentAt + (5 * 60);

            if (time() < $nextAllowedTime) {
                throw new Exception(
                    "A verification code was recently sent. Please wait before requesting another code."
                );
            }
        }

        /*
         * Invalidate previous reset requests.
         */
        $this->passwordResetRepository
            ->invalidatePreviousRequests($email);

        /*
         * Generate a new 6-digit verification code.
         */
        $code = (string) random_int(100000, 999999);

        /*
         * Never store the actual code in the database.
         */
        $codeHash = password_hash(
            $code,
            PASSWORD_DEFAULT
        );

        $expiresAt = date(
            'Y-m-d H:i:s',
            time() + (10 * 60)
        );

        $now = date('Y-m-d H:i:s');

        /*
         * Save the hashed code.
         */
        $this->passwordResetRepository->create(
            $user->id,
            $email,
            $codeHash,
            $expiresAt,
            $now
        );

        /*
         * Send the actual code to the user's email.
         */
        $this->mailService->sendPasswordResetCode(
            $email,
            $code
        );

        /*
         * The code is intentionally NOT returned.
         */
        return null;
    }

    public function verifyResetCode(
        string $email,
        string $code
    ): string {
        $email = trim(strtolower($email));
        $code = trim($code);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception(
                "Invalid or expired verification code."
            );
        }

        if (!preg_match('/^\d{6}$/', $code)) {
            throw new Exception(
                "Invalid or expired verification code."
            );
        }

        $request = $this->passwordResetRepository
            ->findLatestByEmail($email);

        if (!$request) {
            throw new Exception(
                "Invalid or expired verification code."
            );
        }

        /*
         * Do not allow a previously verified request
         * to be verified again.
         */
        if (!empty($request['verified_at'])) {
            throw new Exception(
                "This verification request has already been used."
            );
        }

        /*
         * Check whether verification is temporarily blocked.
         */
        if (
            !empty($request['blocked_until']) &&
            strtotime($request['blocked_until']) > time()
        ) {
            throw new Exception(
                "Too many attempts. Please try again later."
            );
        }

        /*
         * Check whether the verification code has expired.
         */
        if (
            empty($request['expires_at']) ||
            strtotime($request['expires_at']) <= time()
        ) {
            throw new Exception(
                "Verification code has expired."
            );
        }

        /*
         * Verify the code.
         */
        if (
            !password_verify(
                $code,
                $request['code_hash']
            )
        ) {

            /*
             * Count this failed attempt.
             */
            $this->passwordResetRepository
                ->incrementAttempts(
                    (int) $request['id']
                );

            /*
             * The third incorrect attempt immediately
             * triggers a 1-hour verification block.
             */
            $newAttempts = (int) $request['attempts'] + 1;

            if ($newAttempts >= 3) {

                $blockedUntil = date(
                    'Y-m-d H:i:s',
                    time() + (60 * 60)
                );

                $this->passwordResetRepository->block(
                    (int) $request['id'],
                    $blockedUntil
                );

                throw new Exception(
                    "Too many incorrect attempts. Password reset verification has been blocked for 1 hour."
                );
            }

            throw new Exception(
                "Invalid verification code."
            );
        }

        /*
         * The verification code is correct.
         */
        $this->passwordResetRepository->markVerified(
            (int) $request['id']
        );

        /*
         * Generate a cryptographically secure
         * password-reset token.
         */
        $resetToken = bin2hex(
            random_bytes(32)
        );

        /*
         * Store only the SHA-256 hash.
         */
        $resetTokenHash = hash(
            'sha256',
            $resetToken
        );

        /*
         * Reset token expires after 15 minutes.
         */
        $resetTokenExpiresAt = date(
            'Y-m-d H:i:s',
            time() + (15 * 60)
        );

        $this->passwordResetRepository->saveResetToken(
            (int) $request['id'],
            $resetTokenHash,
            $resetTokenExpiresAt
        );

        /*
         * Return the raw token to the frontend.
         *
         * The database contains only its hash.
         */
        return $resetToken;
    }


    public function resetPassword(
        string $resetToken,
        string $password,
        string $confirmPassword
    ): void {
        $resetToken = trim($resetToken);

        if ($resetToken === '') {
            throw new Exception(
                "Invalid reset token."
            );
        }

        if (strlen($password) < 8) {
            throw new Exception(
                "Password must be at least 8 characters."
            );
        }

        if ($password !== $confirmPassword) {
            throw new Exception(
                "Passwords do not match."
            );
        }

        /*
         * Hash the token supplied by the client.
         */
        $resetTokenHash = hash(
            'sha256',
            $resetToken
        );

        /*
         * Find the reset request.
         *
         * The repository also ensures that the token
         * has not expired.
         */
        $request = $this->passwordResetRepository
            ->findByResetTokenHash($resetTokenHash);

        if (!$request) {
            throw new Exception(
                "Invalid or expired reset token."
            );
        }

        /*
         * Make sure the email verification step
         * was completed.
         */
        if (empty($request['verified_at'])) {
            throw new Exception(
                "Password reset has not been verified."
            );
        }

        /*
         * Make sure the request belongs to a real user.
         */
        if (empty($request['user_id'])) {
            throw new Exception(
                "Invalid password reset request."
            );
        }

        /*
         * Hash the new password.
         */
        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        if ($passwordHash === false) {
            throw new Exception(
                "Unable to secure the new password."
            );
        }

        /*
         * Update the password.
         */
        $this->userRepository->updatePassword(
            (int) $request['user_id'],
            $passwordHash
        );

        /*
         * Immediately invalidate the reset token.
         */
        $this->passwordResetRepository->consume(
            (int) $request['id']
        );

        /*
         * Invalidate all existing sessions.
         *
         * This logs the user out of existing devices.
         */
        $this->sessionRepository->deleteAllForUser(
            (int) $request['user_id']
        );
    }

}