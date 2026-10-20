<?php

declare(strict_types=1);

namespace Repositories;

use App\Repositories\SessionRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use PDOException;

final class SessionRepositoryTest extends TestCase
{
    private PDO $pdo;
    private SessionRepository $repository;

    /** @var string[] Raw test tokens whose hashes may need cleanup */
    private array $testTokens = [];

    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/bootstrap.php';

        $host = $_ENV['DB_HOST'];
        $user = $_ENV['DB_USER'];
        $password = $_ENV['DB_PASSWORD'];

        $this->pdo = new PDO(
            "mysql:host={$host};dbname=hairconnect_test_db;charset=utf8mb4",
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $this->pdo->exec("SET time_zone = '+00:00'");

        $this->repository = new SessionRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            foreach ($this->testTokens as $token) {
                $stmt = $this->pdo->prepare(
                    'DELETE FROM sessions WHERE refresh_token = :token'
                );

                $stmt->execute([
                    'token' => hash('sha256', $token),
                ]);
            }
        }

        $this->testTokens = [];

        parent::tearDown();
    }

    private function newTestToken(): string
    {
        $token = bin2hex(random_bytes(32));

        $this->testTokens[] = $token;

        return $token;
    }

    private function insertSession(
        int $userId,
        string $token,
        string $expiresAt
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (user_id, refresh_token, expires_at)
             VALUES (:user_id, :refresh_token, :expires_at)'
        );

        $stmt->execute([
            'user_id' => $userId,
            'refresh_token' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);
    }

    private function sessionExists(string $token): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM sessions
             WHERE refresh_token = :refresh_token'
        );

        $stmt->execute([
            'refresh_token' => hash('sha256', $token),
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function testSuccessfulRotationConsumesOldTokenAndStoresNewHash(): void
    {
        $oldToken = $this->newTestToken();
        $newToken = $this->newTestToken();

        $userId = 987654321;
        $expiresAt = gmdate('Y-m-d H:i:s', time() + 3600);

        $this->insertSession($userId, $oldToken, $expiresAt);

        $session = $this->repository->rotateToken(
            $oldToken,
            $newToken,
            gmdate('Y-m-d H:i:s', time() + 604800)
        );

        $this->assertIsArray($session);
        $this->assertSame($userId, (int) $session['user_id']);

        $this->assertFalse(
            $this->sessionExists($oldToken),
            'The previous refresh token should be consumed.'
        );

        $this->assertTrue(
            $this->sessionExists($newToken),
            'The replacement token hash should be stored.'
        );

        // Verify that the raw token itself was not stored.
        $stmt = $this->pdo->prepare(
            'SELECT refresh_token FROM sessions
             WHERE refresh_token = :refresh_token'
        );

        $stmt->execute([
            'refresh_token' => hash('sha256', $newToken),
        ]);

        $storedHash = $stmt->fetchColumn();

        $this->assertSame(hash('sha256', $newToken), $storedHash);
        $this->assertNotSame($newToken, $storedHash);
    }

    public function testReusedRefreshTokenIsRejected(): void
    {
        $oldToken = $this->newTestToken();
        $newToken = $this->newTestToken();
        $anotherToken = $this->newTestToken();

        $this->insertSession(
            987654322,
            $oldToken,
            gmdate('Y-m-d H:i:s', time() + 3600)
        );

        $firstResult = $this->repository->rotateToken(
            $oldToken,
            $newToken,
            gmdate('Y-m-d H:i:s', time() + 604800)
        );

        $this->assertIsArray($firstResult);

        $secondResult = $this->repository->rotateToken(
            $oldToken,
            $anotherToken,
            gmdate('Y-m-d H:i:s', time() + 604800)
        );

        $this->assertNull(
            $secondResult,
            'A consumed refresh token must not be reusable.'
        );

        $this->assertTrue($this->sessionExists($newToken));
        $this->assertFalse($this->sessionExists($anotherToken));
    }

    public function testExpiredRefreshTokenIsRejected(): void
    {
        $expiredToken = $this->newTestToken();
        $replacementToken = $this->newTestToken();

        $this->insertSession(
            987654323,
            $expiredToken,
            gmdate('Y-m-d H:i:s', time() - 3600)
        );

        $result = $this->repository->rotateToken(
            $expiredToken,
            $replacementToken,
            gmdate('Y-m-d H:i:s', time() + 604800)
        );

        $this->assertNull(
            $result,
            'An expired refresh token must not be rotated.'
        );

        $this->assertTrue(
            $this->sessionExists($expiredToken),
            'The rejected expired session should remain unchanged.'
        );

        $this->assertFalse($this->sessionExists($replacementToken));
    }

    public function testFailedReplacementInsertRollsBackOldTokenDeletion(): void
    {
        $oldToken = $this->newTestToken();
        $existingToken = $this->newTestToken();

        $this->insertSession(
            987654324,
            $oldToken,
            gmdate('Y-m-d H:i:s', time() + 3600)
        );

        // This creates a deliberate unique-index collision when the
        // repository tries to insert the replacement token.
        $this->insertSession(
            987654325,
            $existingToken,
            gmdate('Y-m-d H:i:s', time() + 3600)
        );

        try {
            $this->repository->rotateToken(
                $oldToken,
                $existingToken,
                gmdate('Y-m-d H:i:s', time() + 604800)
            );

            $this->fail(
                'The duplicate token hash should cause an insertion failure.'
            );
        } catch (PDOException $e) {
            $this->assertSame(
                '23000',
                $e->getCode(),
                'The failure should be a database integrity violation.'
            );
        }

        $this->assertTrue(
            $this->sessionExists($oldToken),
            'The transaction should restore the old token after failure.'
        );

        $this->assertTrue(
            $this->sessionExists($existingToken),
            'The pre-existing session must remain intact.'
        );
    }


    public function testConcurrentRotationAllowsOnlyOneSuccessfulRequest(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required.');
        }

        $oldToken = $this->newTestToken();
        $newTokenA = $this->newTestToken();
        $newTokenB = $this->newTestToken();

        $this->insertSession(
            987654326,
            $oldToken,
            gmdate('Y-m-d H:i:s', time() + 3600)
        );

        $worker = __DIR__ . '/SessionRotationWorker.php';
        $directory = sys_get_temp_dir()
            . '/hairconnect-rotation-'
            . bin2hex(random_bytes(8));

        if (!mkdir($directory, 0700)) {
            $this->fail('Unable to create temporary worker directory.');
        }

        $startFile = $directory . '/start';
        $resultA = $directory . '/result-a.json';
        $resultB = $directory . '/result-b.json';

        $processes = [];
        $pids = [];

        try {
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg($worker);

            $argumentsA = ' '
                . escapeshellarg($oldToken)
                . ' '
                . escapeshellarg($newTokenA)
                . ' '
                . escapeshellarg($startFile)
                . ' '
                . escapeshellarg($resultA);

            $argumentsB = ' '
                . escapeshellarg($oldToken)
                . ' '
                . escapeshellarg($newTokenB)
                . ' '
                . escapeshellarg($startFile)
                . ' '
                . escapeshellarg($resultB);

            $pidA = pcntl_fork();

            if ($pidA === -1) {
                $this->fail('Unable to fork worker A.');
            }

            if ($pidA === 0) {
                pcntl_exec(
                    PHP_BINARY,
                    [
                        $worker,
                        $oldToken,
                        $newTokenA,
                        $startFile,
                        $resultA,
                    ]
                );

                exit(127);
            }

            $pids[] = $pidA;

            $pidB = pcntl_fork();

            if ($pidB === -1) {
                $this->fail('Unable to fork worker B.');
            }

            if ($pidB === 0) {
                pcntl_exec(
                    PHP_BINARY,
                    [
                        $worker,
                        $oldToken,
                        $newTokenB,
                        $startFile,
                        $resultB,
                    ]
                );

                exit(127);
            }

            $pids[] = $pidB;

            // Both workers begin after this signal.
            touch($startFile);

            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);

                $this->assertTrue(
                    pcntl_wifexited($status),
                    "Worker {$pid} did not exit normally."
                );

                $this->assertSame(
                    0,
                    pcntl_wexitstatus($status),
                    "Worker {$pid} failed."
                );
            }

            $dataA = json_decode(
                (string) file_get_contents($resultA),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $dataB = json_decode(
                (string) file_get_contents($resultB),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $this->assertNull($dataA['error']);
            $this->assertNull($dataB['error']);

            $successCount = (int) $dataA['success']
                + (int) $dataB['success'];

            $this->assertSame(
                1,
                $successCount,
                'Exactly one concurrent rotation should succeed.'
            );

            $this->assertFalse($this->sessionExists($oldToken));

            $this->assertSame(
                1,
                (int) $this->sessionExists($newTokenA)
                + (int) $this->sessionExists($newTokenB),
                'Exactly one replacement token should be stored.'
            );
        } finally {
            // Reap any remaining workers before deleting temporary files.
            foreach ($pids as $pid) {
                $result = pcntl_waitpid($pid, $status, WNOHANG);

                if ($result === 0) {
                    pcntl_waitpid($pid, $status);
                }
            }

            foreach ([$startFile, $resultA, $resultB] as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }

            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }
}