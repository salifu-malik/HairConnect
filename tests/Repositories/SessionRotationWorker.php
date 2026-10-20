<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Repositories\SessionRepository;

if ($argc !== 5) {
    fwrite(
        STDERR,
        "Usage: php SessionRotationWorker.php <old-token> <new-token> <start-file> <result-file>\n"
    );
    exit(2);
}

[, $oldToken, $newToken, $startFile, $resultFile] = $argv;

try {
    $host = $_ENV['DB_HOST'];
    $user = $_ENV['DB_USER'];
    $password = $_ENV['DB_PASSWORD'];

    // Every worker creates its own connection.
    $pdo = new PDO(
        "mysql:host={$host};dbname=hairconnect_test_db;charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $pdo->exec("SET time_zone = '+00:00'");

    $repository = new SessionRepository($pdo);

    // Wait until the parent releases all workers.
    $deadline = microtime(true) + 15;

    while (!file_exists($startFile)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Timed out waiting for start signal.');
        }

        usleep(1000);
    }

    $result = $repository->rotateToken(
        $oldToken,
        $newToken,
        gmdate('Y-m-d H:i:s', time() + 604800)
    );

    file_put_contents(
        $resultFile,
        json_encode([
            'success' => $result !== null,
            'error' => null,
        ], JSON_THROW_ON_ERROR),
        LOCK_EX
    );

    exit(0);
} catch (Throwable $e) {
    file_put_contents(
        $resultFile,
        json_encode([
            'success' => false,
            'error' => get_class($e) . ': ' . $e->getMessage(),
        ]),
        LOCK_EX
    );

    exit(1);
}