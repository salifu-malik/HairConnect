<?php


namespace App\Services;

use App\Repositories\UserRepository;
use App\Repositories\SessionRepository;
use App\Repositories\PasswordResetRepository;
use App\Validators\AuthValidator;
use App\Helpers\JwtHelper;
use Exception;
use App\Services\MailService;


class AuthService
{
    private UserRepository $userRepository;
    private SessionRepository $sessionRepository;
    private PasswordResetRepository $passwordResetRepository;
    private MailService $mailService;

    public function __construct(UserRepository $userRepository, SessionRepository $sessionRepository,  PasswordResetRepository $passwordResetRepository, MailService $mailService)
    {
        $this->userRepository = $userRepository;
        $this->sessionRepository = $sessionRepository;
        $this->passwordResetRepository = $passwordResetRepository;
        $this->mailService = $mailService;
    }

    public function register(array $data): int
    {
        AuthValidator::validateRegistration($data);

        if ($this->userRepository->findByEmail($data['email'])) {
            throw new Exception("Email already exists.");
        }

        // Convert frontend field names to database field names
        $data['first_name'] = $data['firstname'];
        $data['last_name'] = $data['lastname'];

        $userId = $this->userRepository->create($data);

        $this->userRepository->assignRole(
            $userId,
            $data['role']
        );

        return $userId;
    }


    public function login(string $email, string $password)
    {
        $user = $this->userRepository->findByEmail($email);

        if (
            !$user ||
            !password_verify(
                $password,
                $user->getPasswordHash()
            )
        ) {
            throw new Exception("Invalid credentials.");
        }

        $roles = $this->userRepository->getRoles($user->id);

        $user->roles = $roles;

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
        if (!$session || $session['expires_at'] < date('Y-m-d H:i:s')) {
            throw new Exception("Invalid or expired refresh token.");
        }

        $user = $this->userRepository->findById((int)$session['user_id']);
        if (!$user) {
            throw new Exception("User not found.");
        }

        $accessToken = JwtHelper::encode(['user_id' => $user->id, 'email' => $user->email], 3600);
        
        // Rotate refresh token
        $this->sessionRepository->deleteByToken($refreshToken);
        $newRefreshToken = bin2hex(random_bytes(64));
        $expiresAt = date('Y-m-d H:i:s', time() + (7 * 24 * 3600));
        $this->sessionRepository->save($user->id, $newRefreshToken, $expiresAt);

        return [
            'token' => $accessToken,
            'refresh_token' => $newRefreshToken
        ];
    }

    public function logout(string $refreshToken)
    {
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