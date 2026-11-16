<?php

declare(strict_types=1);

// Force the worker to use the isolated test database.
$_ENV['SUBSCRIPTION_DB_NAME'] = 'subscription_test_db';
putenv('SUBSCRIPTION_DB_NAME=subscription_test_db');

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Helpers\DatabaseManager;
use App\Repositories\PlanRepository;
use App\Repositories\PlanVersionRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\SubscriptionPaymentRepository;
use App\Repositories\SubscriptionEventRepository;
use App\Repositories\UserRepository;
use App\Repositories\BarberRepository;
use App\Repositories\ShopRepository;
use App\Repositories\SubscriptionPaymentAttemptRepository;
use App\Services\PaystackService;
use App\Services\SubscriptionService;

if ($argc !== 5) {
    fwrite(
        STDERR,
        "Usage: php SubscriptionVerificationWorker.php <reference> <user-id> <start-file> <result-file>\n"
    );
    exit(2);
}

[, $reference, $userId, $startFile, $resultFile] = $argv;

try {
    // Force each worker to establish its own PDO connection.
    DatabaseManager::resetConnection('subscription_db');

    $db = DatabaseManager::getConnection('subscription_db');

    // Never allow this worker to use the production database.
    $databaseName = $db->query('SELECT DATABASE()')->fetchColumn();

    if ($databaseName !== 'subscription_test_db') {
        throw new RuntimeException(
            'Worker connected to an unexpected database: '
            . $databaseName
        );
    }

    $db->exec("SET time_zone = '+00:00'");

    $subscriptionRepository = new SubscriptionRepository();
    $paymentRepository = new SubscriptionPaymentRepository();

    $attemptRepository =
        new SubscriptionPaymentAttemptRepository();

    $eventRepository = new SubscriptionEventRepository();

    // This verification operation does not use UserRepository.
    $userRepository = new class extends UserRepository {
        public function __construct()
        {
            // Deliberately avoid connecting to users_db.
        }
    };

    // Simulate a successful Paystack response.
    $paystackService = new class extends PaystackService {
        public function __construct()
        {
            // No real Paystack credentials or network calls.
        }

        public function verifyTransaction(
            string $reference
        ): array {
            return [
                'reference' => $reference,
                'status' => 'success',
                'currency' => 'GHS',
                'amount' => 2500,
            ];
        }
    };


    $subscriptionService = new SubscriptionService(
        new PlanRepository(),
        new PlanVersionRepository(),
        $subscriptionRepository,
        $paymentRepository,
        $userRepository,
        new BarberRepository(),
        new ShopRepository(),
        $eventRepository,
        $attemptRepository,
        $paystackService
    );

    // Wait until the parent releases both workers.
    $deadline = microtime(true) + 15;

    while (!file_exists($startFile)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException(
                'Timed out waiting for start signal.'
            );
        }

        usleep(1000);
    }

    $result = $subscriptionService->verifySubscriptionPayment(
        $reference,
        (int) $userId
    );

    file_put_contents(
        $resultFile,
        json_encode([
            'success' => true,
            'status' => $result['status'] ?? null,
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
            'status' => null,
            'error' => get_class($e) . ': ' . $e->getMessage(),
        ], JSON_THROW_ON_ERROR),
        LOCK_EX
    );

    exit(1);
}
