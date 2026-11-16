<?php

declare(strict_types=1);

use App\Exceptions\AuthenticationException;
use App\Exceptions\EmailNotVerifiedException;
use App\Helpers\RedisManager;
use App\Models\User;
use App\Repositories\PasswordResetRepository;
use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Services\MailService;
use App\Services\RefreshTokenRotationService;
use PHPUnit\Framework\TestCase;

final class AuthServiceRefreshTest extends TestCase
{
    private UserRepository $userRepository;
    private SessionRepository $sessionRepository;
    private PasswordResetRepository $passwordResetRepository;
    private MailService $mailService;
    private RedisManager $redisManager;
    private AuditLogService $auditLogService;
    private RefreshTokenRotationService $refreshTokenRotationService;

    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = $this->createMock(
            UserRepository::class
        );

        $this->sessionRepository = $this->createMock(
            SessionRepository::class
        );

        $this->passwordResetRepository = $this->createMock(
            PasswordResetRepository::class
        );

        $this->mailService = $this->createMock(
            MailService::class
        );

        $this->redisManager = $this->createMock(
            RedisManager::class
        );

        $this->auditLogService = $this->createMock(
            AuditLogService::class
        );

        $this->refreshTokenRotationService = $this->createMock(
            RefreshTokenRotationService::class
        );

        $this->authService = new AuthService(
            $this->userRepository,
            $this->sessionRepository,
            $this->passwordResetRepository,
            $this->mailService,
            $this->redisManager,
            $this->auditLogService,
            $this->refreshTokenRotationService
        );
    }

    public function testEmptyRefreshTokenIsRejectedWithoutRotationCall(): void
    {
        $this->refreshTokenRotationService
            ->expects($this->never())
            ->method('rotate');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage(
            'Invalid or expired refresh token.'
        );

        $this->authService->refresh('');
    }

    public function testInvalidRefreshTokenIsRejected(): void
    {
        $this->refreshTokenRotationService
            ->expects($this->once())
            ->method('rotate')
            ->willReturn([
                'status' => 'invalid_token',
            ]);

        $this->expectException(AuthenticationException::class);

        $this->authService->refresh('invalid-refresh-token');
    }

    public function testInactiveUserCannotRefreshToken(): void
    {
        $this->refreshTokenRotationService
            ->expects($this->once())
            ->method('rotate')
            ->willReturn([
                'status' => 'inactive',
            ]);

        $this->expectException(AuthenticationException::class);

        $this->authService->refresh('inactive-user-token');
    }

    public function testSuspendedUserCannotRefreshToken(): void
    {
        $this->refreshTokenRotationService
            ->expects($this->once())
            ->method('rotate')
            ->willReturn([
                'status' => 'suspended',
            ]);

        $this->expectException(AuthenticationException::class);

        $this->authService->refresh('suspended-user-token');
    }

    public function testUnverifiedEmailCannotRefreshToken(): void
    {
        $this->refreshTokenRotationService
            ->expects($this->once())
            ->method('rotate')
            ->willReturn([
                'status' => 'email_unverified',
            ]);

        $this->expectException(EmailNotVerifiedException::class);
        $this->expectExceptionMessage(
            'Email verification is required.'
        );

        $this->authService->refresh('unverified-user-token');
    }

    public function testMissingUserCannotRefreshToken(): void
    {
        $this->refreshTokenRotationService
            ->expects($this->once())
            ->method('rotate')
            ->willReturn([
                'status' => 'user_not_found',
            ]);

        $this->expectException(AuthenticationException::class);

        $this->authService->refresh('missing-user-token');
    }

    public function testSuccessfulRefreshReturnsCoordinatorTokens(): void
    {
        $user = new User([
            'id' => 42,
            'public_id' => 'test-public-id',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'phone' => '0200000000',
            'password_hash' => 'test-password-hash',
            'status' => 'active',
            'email_verified_at' => '2026-01-01 00:00:00',
        ]);

        $accessToken = 'coordinator-generated-access-token';
        $refreshToken = str_repeat('a', 128);

        $this->refreshTokenRotationService
            ->expects($this->once())
            ->method('rotate')
            ->with(
                'valid-refresh-token',
                $this->callback(
                    fn (string $token): bool =>
                        preg_match('/^[a-f0-9]{128}$/', $token) === 1
                ),
                $this->callback(
                    fn (string $expiry): bool =>
                        strtotime($expiry) > time()
                )
            )
            ->willReturn([
                'status' => 'rotated',
                'user' => $user,
                'token' => $accessToken,
                'refresh_token' => $refreshToken,
            ]);

        $this->auditLogService
            ->expects($this->once())
            ->method('auth')
            ->with(
                'TOKEN_REFRESH',
                $user,
                'Access token refreshed successfully.'
            );

        $result = $this->authService->refresh(
            'valid-refresh-token'
        );

        $this->assertSame($accessToken, $result['token']);
        $this->assertSame($refreshToken, $result['refresh_token']);

        $this->assertCount(2, $result);
    }
}