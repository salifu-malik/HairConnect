<?php

declare(strict_types=1);

use App\Helpers\JwtHelper;
use App\Issuers\AccessTokenIssuerInterface;
use App\Issuers\JwtAccessTokenIssuer;
use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;
use App\Services\RefreshTokenRotationService;
use PHPUnit\Framework\TestCase;

final class RefreshTokenRotationServiceTest extends TestCase
{
    private PDO $db;
    private SessionRepository $sessionRepository;
    private UserRepository $userRepository;

    private array $createdUserIds = [];
    private array $createdRoleIds = [];
    private array $createdSessionIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = new PDO(
            "mysql:host={$_ENV['DB_HOST']};dbname=hairconnect_test_db;charset=utf8mb4",
            $_ENV['DB_USER'],
            $_ENV['DB_PASSWORD'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $databaseName = $this->db
            ->query('SELECT DATABASE()')
            ->fetchColumn();

        self::assertSame(
            'hairconnect_test_db',
            $databaseName,
            'Tests must never write to the production database.'
        );

        $this->db->exec("SET time_zone = '+00:00'");

        // Both repositories receive the exact same PDO instance.
        $this->sessionRepository = new SessionRepository($this->db);
        $this->userRepository = new UserRepository($this->db);
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {

            foreach ($this->createdSessionIds as $id) {
                $stmt = $this->db->prepare(
                    'DELETE FROM sessions WHERE id = :id'
                );

                $stmt->execute(['id' => $id]);
            }

            foreach ($this->createdUserIds as $id) {
                $stmt = $this->db->prepare(
                    'DELETE FROM sessions WHERE user_id = :id'
                );

                $stmt->execute(['id' => $id]);
            }


            foreach ($this->createdUserIds as $id) {
                $stmt = $this->db->prepare(
                    'DELETE FROM user_roles WHERE user_id = :id'
                );

                $stmt->execute(['id' => $id]);
            }

            foreach ($this->createdRoleIds as $id) {
                $stmt = $this->db->prepare(
                    'DELETE FROM roles WHERE id = :id'
                );

                $stmt->execute(['id' => $id]);
            }

            foreach ($this->createdUserIds as $id) {
                $stmt = $this->db->prepare(
                    'DELETE FROM users WHERE id = :id'
                );

                $stmt->execute(['id' => $id]);
            }
        }

        parent::tearDown();
    }

    private function createCoordinator(
        ?AccessTokenIssuerInterface $issuer = null
    ): RefreshTokenRotationService {
        return new RefreshTokenRotationService(
            $this->db,
            $this->sessionRepository,
            $this->userRepository,
            $issuer ?? new JwtAccessTokenIssuer()
        );
    }

    private function createUser(
        string $status = 'active',
        ?string $emailVerifiedAt = '2026-01-01 00:00:00'
    ): int {
        $bytes = random_bytes(16);

        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        $hex = bin2hex($bytes);

        $publicId = sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );

        $stmt = $this->db->prepare("
            INSERT INTO users (
                first_name,
                last_name,
                email,
                password_hash,
                status,
                email_verified_at,
                public_id
            )
            VALUES (
                'Refresh',
                'Test',
                :email,
                :password_hash,
                :status,
                :email_verified_at,
                :public_id
            )
        ");

        $stmt->execute([
            'email' => 'refresh-' . bin2hex(random_bytes(8)) . '@example.test',
            'password_hash' => password_hash(
                'test-password',
                PASSWORD_DEFAULT
            ),
            'status' => $status,
            'email_verified_at' => $emailVerifiedAt,
            'public_id' => $publicId,
        ]);

        $userId = (int) $this->db->lastInsertId();

        $this->createdUserIds[] = $userId;

        return $userId;
    }

    private function createRole(string $name): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO roles (name) VALUES (:name)'
        );

        $stmt->execute(['name' => $name]);

        $roleId = (int) $this->db->lastInsertId();

        $this->createdRoleIds[] = $roleId;

        return $roleId;
    }

    private function assignRole(int $userId, int $roleId): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO user_roles (user_id, role_id)
            VALUES (:user_id, :role_id)
        ");

        $stmt->execute([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
    }

    private function createRefreshSession(
        int $userId,
        string $refreshToken
    ): int {
        $expiresAt = gmdate('Y-m-d H:i:s', time() + 3600);

        $this->sessionRepository->save(
            $userId,
            $refreshToken,
            $expiresAt
        );

        $sessionId = (int) $this->db->lastInsertId();

        $this->createdSessionIds[] = $sessionId;

        return $sessionId;
    }

    public function testInvalidRotationArgumentsAreRejectedBeforeTransaction(): void
    {
        $validToken = bin2hex(random_bytes(64));
        $anotherToken = bin2hex(random_bytes(64));

        $invalidInputs = [
            [
                '',
                $anotherToken,
                gmdate('Y-m-d H:i:s', time() + 3600),
            ],
            [
                $validToken,
                '',
                gmdate('Y-m-d H:i:s', time() + 3600),
            ],
            [
                $validToken,
                $validToken,
                gmdate('Y-m-d H:i:s', time() + 3600),
            ],
            [
                $validToken,
                $anotherToken,
                'not-a-valid-date',
            ],
            [
                $validToken,
                $anotherToken,
                gmdate('Y-m-d H:i:s', time() - 3600),
            ],
        ];

        foreach ($invalidInputs as $inputs) {
            try {
                $this->createCoordinator()->rotate(
                    $inputs[0],
                    $inputs[1],
                    $inputs[2]
                );

                self::fail(
                    'Invalid rotation arguments should be rejected.'
                );
            } catch (InvalidArgumentException $exception) {
                self::assertFalse(
                    $this->db->inTransaction(),
                    'Invalid arguments must be rejected before opening a transaction.'
                );
            }
        }
    }

    public function testSuccessfulRotationIssuesJwtAndReplacesRefreshToken(): void
    {
        $userId = $this->createUser();

        $barberRoleId = $this->createRole(
            'test-barber-' . bin2hex(random_bytes(4))
        );

        $customerRoleId = $this->createRole(
            'test-customer-' . bin2hex(random_bytes(4))
        );

        $this->assignRole($userId, $barberRoleId);
        $this->assignRole($userId, $customerRoleId);

        $oldToken = bin2hex(random_bytes(64));
        $newToken = bin2hex(random_bytes(64));

        $this->createRefreshSession($userId, $oldToken);

        $result = $this->createCoordinator()->rotate(
            $oldToken,
            $newToken,
            gmdate('Y-m-d H:i:s', time() + 604800)
        );

        self::assertSame('rotated', $result['status']);
        self::assertSame($newToken, $result['refresh_token']);
        self::assertNotEmpty($result['token']);

        self::assertSame(
            $userId,
            $result['user']->id
        );

        self::assertNull(
            $this->sessionRepository->findByToken($oldToken),
            'The old refresh token must be consumed.'
        );

        self::assertNotNull(
            $this->sessionRepository->findByToken($newToken),
            'The new refresh token must exist.'
        );

        $payload = JwtHelper::decode($result['token']);

        self::assertIsArray($payload);
        self::assertSame($userId, $payload['user_id']);
        self::assertSame($result['user']->email, $payload['email']);
        self::assertEqualsCanonicalizing(
            $result['user']->roles,
            $payload['roles']
        );

        self::assertCount(2, $payload['roles']);
    }

    public function testInvalidRefreshTokenDoesNotChangeDatabase(): void
    {
        $invalidToken = bin2hex(random_bytes(64));
        $newToken = bin2hex(random_bytes(64));

        $result = $this->createCoordinator()->rotate(
            $invalidToken,
            $newToken,
            gmdate('Y-m-d H:i:s', time() + 604800)
        );

        self::assertSame('invalid_token', $result['status']);

        self::assertNull(
            $this->sessionRepository->findByToken($newToken)
        );
    }

    public function testMissingUserDoesNotConsumeRefreshToken(): void
    {
        $oldToken = bin2hex(random_bytes(64));
        $newToken = bin2hex(random_bytes(64));

        // No corresponding user record is created.
        $this->createRefreshSession(2000000000, $oldToken);

        $result = $this->createCoordinator()->rotate(
            $oldToken,
            $newToken,
            gmdate('Y-m-d H:i:s', time() + 604800)
        );

        self::assertSame('user_not_found', $result['status']);

        self::assertNotNull(
            $this->sessionRepository->findByToken($oldToken)
        );

        self::assertNull(
            $this->sessionRepository->findByToken($newToken)
        );
    }

    public function testIneligibleUsersCannotRotateRefreshTokens(): void
    {
        $scenarios = [
            [
                'status' => 'inactive',
                'verified_at' => '2026-01-01 00:00:00',
                'expected' => 'inactive',
            ],
            [
                'status' => 'suspended',
                'verified_at' => '2026-01-01 00:00:00',
                'expected' => 'suspended',
            ],
            [
                'status' => 'active',
                'verified_at' => null,
                'expected' => 'email_unverified',
            ],
        ];

        foreach ($scenarios as $scenario) {
            $userId = $this->createUser(
                $scenario['status'],
                $scenario['verified_at']
            );

            $oldToken = bin2hex(random_bytes(64));
            $newToken = bin2hex(random_bytes(64));

            $this->createRefreshSession($userId, $oldToken);

            $result = $this->createCoordinator()->rotate(
                $oldToken,
                $newToken,
                gmdate('Y-m-d H:i:s', time() + 604800)
            );

            self::assertSame(
                $scenario['expected'],
                $result['status']
            );

            self::assertNotNull(
                $this->sessionRepository->findByToken($oldToken)
            );

            self::assertNull(
                $this->sessionRepository->findByToken($newToken)
            );
        }
    }

    public function testJwtIssuanceFailureRollsBackRefreshTokenDeletion(): void
    {
        $userId = $this->createUser();

        $oldToken = bin2hex(random_bytes(64));
        $newToken = bin2hex(random_bytes(64));

        $this->createRefreshSession($userId, $oldToken);

        $issuer = $this->createMock(
            AccessTokenIssuerInterface::class
        );

        $issuer->expects(self::once())
            ->method('issue')
            ->willThrowException(
                new RuntimeException('Simulated JWT signing failure.')
            );

        try {
            $this->createCoordinator($issuer)->rotate(
                $oldToken,
                $newToken,
                gmdate('Y-m-d H:i:s', time() + 604800)
            );

            self::fail('Expected JWT issuance to fail.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Simulated JWT signing failure.',
                $exception->getMessage()
            );
        }

        self::assertNotNull(
            $this->sessionRepository->findByToken($oldToken),
            'The old token must survive JWT issuance failure.'
        );

        self::assertNull(
            $this->sessionRepository->findByToken($newToken),
            'The replacement token must not be persisted.'
        );

        self::assertFalse($this->db->inTransaction());
    }

    public function testDuplicateReplacementTokenFailureRollsBackDeletion(): void
    {
        $userId = $this->createUser();

        $oldToken = bin2hex(random_bytes(64));
        $newToken = bin2hex(random_bytes(64));

        $this->createRefreshSession($userId, $oldToken);

        // Pre-create a different session using the replacement token.
        $collisionSessionId = $this->createRefreshSession(
            2000000001,
            $newToken
        );

        $issuer = $this->createMock(
            AccessTokenIssuerInterface::class
        );

        $issuer->expects(self::once())
            ->method('issue')
            ->willReturn('simulated-access-token');

        try {
            $this->createCoordinator($issuer)->rotate(
                $oldToken,
                $newToken,
                gmdate('Y-m-d H:i:s', time() + 604800)
            );

            self::fail('Expected duplicate token insertion to fail.');
        } catch (PDOException $exception) {
            self::assertSame(
                '23000',
                $exception->getCode()
            );
        }

        self::assertNotNull(
            $this->sessionRepository->findByToken($oldToken),
            'The old token deletion must be rolled back.'
        );

        $collision = $this->sessionRepository->findByToken($newToken);

        self::assertNotNull($collision);

        self::assertSame(
            $collisionSessionId,
            (int) $collision['id'],
            'The original collision session must remain unchanged.'
        );

        self::assertFalse($this->db->inTransaction());
    }
}