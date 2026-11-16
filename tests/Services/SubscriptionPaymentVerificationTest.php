<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Helpers\DatabaseManager;
use App\Repositories\PlanRepository;
use App\Repositories\PlanVersionRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\SubscriptionPaymentRepository;
use App\Repositories\SubscriptionEventRepository;
use App\Repositories\UserRepository;
use App\Repositories\BarberRepository;
use App\Repositories\SubscriptionPaymentAttemptRepository;
use App\Repositories\ShopRepository;
use App\Services\PaystackService;
use App\Services\SubscriptionService;
use PDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SubscriptionPaymentVerificationTest extends TestCase
{
    private PDO $db;

    private SubscriptionRepository $subscriptionRepository;
    private SubscriptionPaymentRepository $paymentRepository;
    private SubscriptionEventRepository $eventRepository;
    private SubscriptionPaymentAttemptRepository $attemptRepository;

    private SubscriptionService $subscriptionService;

    private int $userId;
    private int $beneficiaryId;
    private int $planId;
    private int $planVersionId;
    private int $subscriptionId;
    private int $paymentId;

    private string $reference;

    private array $paystackResponse;

    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/bootstrap.php';

        // Discard any previously cached subscription connection.
        DatabaseManager::resetConnection('subscription_db');

        $this->db = DatabaseManager::getConnection(
            'subscription_db'
        );

        // Safety check: never run this test against production.
        $databaseName = $this->db
            ->query('SELECT DATABASE()')
            ->fetchColumn();

        $this->assertSame(
            'subscription_test_db',
            $databaseName,
            'Integration tests must use subscription_test_db.'
        );

        $this->db->exec("SET time_zone = '+00:00'");

        $this->subscriptionRepository =
            new SubscriptionRepository();

        $this->paymentRepository =
            new SubscriptionPaymentRepository();

        $this->attemptRepository =
            new SubscriptionPaymentAttemptRepository();

        $this->eventRepository =
            new SubscriptionEventRepository();

        // Generate unique test identifiers.
        $this->userId = random_int(100000000, 999999999);
        $this->beneficiaryId = random_int(100000000, 999999999);

        $this->reference =
            'TEST_SUB_' . bin2hex(random_bytes(12));

        // Create a test plan.
        $stmt = $this->db->prepare("
         INSERT INTO plans (
    name,
    role,
    scope,
    features
)
VALUES (
    :name,
    'BARBER',
    'HOME',
    :features
)");

        $stmt->execute([
            'name' => 'Integration Test Plan '
                . bin2hex(random_bytes(4)),
            'features' => json_encode([
                'appointments' => true,
                'analytics' => true,
            ]),
        ]);

        $this->planId = (int) $this->db->lastInsertId();

        // Create an approved plan version.
        $stmt = $this->db->prepare("
            INSERT INTO plan_versions (
                plan_id,
                monthly_price,
                yearly_discount_percent,
                status,
                proposed_by
            )
            VALUES (
                :plan_id,
                25.00,
                10.00,
                'approved',
                :proposed_by
            )
        ");

        $stmt->execute([
            'plan_id' => $this->planId,
            'proposed_by' => $this->userId,
        ]);

        $this->planVersionId =
            (int) $this->db->lastInsertId();

        // Create a valid pending HOME entitlement.
        $subscription =
            $this->subscriptionRepository->createEntitlement(
                $this->userId,
                $this->planId,
                $this->planVersionId,
                'monthly',
                'HOME',
                'BARBER',
                $this->beneficiaryId
            );

        $this->assertNotNull($subscription);

        $this->subscriptionId = $subscription->id;

        $this->assertSame('VALID', $subscription->entitlementStatus);
        $this->assertSame('HOME', $subscription->scope);
        $this->assertSame('BARBER', $subscription->beneficiaryType);
        $this->assertSame(
            $this->beneficiaryId,
            $subscription->beneficiaryId
        );
        $this->assertSame('pending', $subscription->status);

        // Create its pending payment.
        $payment =
            $this->paymentRepository->create(
                $this->subscriptionId,
                25.00,
                'GHS',
                $this->reference
            );

        $this->assertNotNull($payment);

        $this->paymentId = $payment->id;


        $attempt = $this->attemptRepository->create(
            $this->paymentId,
            1,
            $this->reference
        );

        $this->assertNotNull($attempt);
        $this->assertSame('initializing', $attempt->status);

        // Create a Paystack stub.
        // No real API requests will be made.
        $paystackMock = $this->createStub(
            PaystackService::class
        );

        $this->paystackResponse = [
            'reference' => $this->reference,
            'status' => 'success',
            'currency' => 'GHS',
            'amount' => 2500,
        ];

        $paystackMock
            ->method('verifyTransaction')
            ->willReturnCallback(
                fn(): array => $this->paystackResponse
            );


        // These dependencies are not used by payment verification.
//        $planRepository = $this->createMock(
//            PlanRepository::class
//        );
//
//        $planVersionRepository = $this->createMock(
//            PlanVersionRepository::class
//        );
//
//        $userRepository = $this->createMock(
//            UserRepository::class
//        );



        $this->subscriptionService = new SubscriptionService(
            $this->createMock(PlanRepository::class),
            $this->createMock(PlanVersionRepository::class),
            $this->subscriptionRepository,
            $this->paymentRepository,
            $this->createMock(UserRepository::class),
            $this->createMock(BarberRepository::class),
            $this->createMock(ShopRepository::class),
            $this->eventRepository,
            $this->attemptRepository,
            $paystackMock
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            // Remove test data in dependency-safe order.
            if (isset($this->subscriptionId)) {
                // Delete subscription events first.
                $stmt = $this->db->prepare("
                    DELETE FROM subscription_events
                    WHERE subscription_id = :id
                ");

                $stmt->execute([
                    'id' => $this->subscriptionId,
                ]);

                // Delete payment attempts before parent payments.
                $stmt = $this->db->prepare("
                    DELETE spa
                    FROM subscription_payment_attempts spa
                    INNER JOIN subscription_payments sp
                        ON sp.id = spa.payment_id
                    WHERE sp.subscription_id = :id
                ");

                $stmt->execute([
                    'id' => $this->subscriptionId,
                ]);

                $stmt = $this->db->prepare("
                    DELETE FROM subscription_payments
                    WHERE subscription_id = :id
                ");

                $stmt->execute([
                    'id' => $this->subscriptionId,
                ]);

                $stmt = $this->db->prepare("
                    DELETE FROM subscriptions
                    WHERE id = :id
                ");

                $stmt->execute([
                    'id' => $this->subscriptionId,
                ]);
            }

            if (isset($this->planVersionId)) {
                $stmt = $this->db->prepare("
                    DELETE FROM plan_versions
                    WHERE id = :id
                ");

                $stmt->execute([
                    'id' => $this->planVersionId,
                ]);
            }

            if (isset($this->planId)) {
                $stmt = $this->db->prepare("
                    DELETE FROM plans
                    WHERE id = :id
                ");

                $stmt->execute([
                    'id' => $this->planId,
                ]);
            }
        }

        parent::tearDown();
    }

    public function testReconciliationStatusUpdatesAreIdempotent(): void
    {
        $attempt = $this->attemptRepository
            ->findByReference($this->reference);

        $this->assertNotNull($attempt);
        $this->assertSame('not_required', $attempt->reconciliationStatus);

        // First transition.
        $this->assertTrue(
            $this->attemptRepository->updateReconciliationStatus(
                $attempt->id,
                'pending_review'
            )
        );

        // Repeating the same transition must still succeed.
        $this->assertTrue(
            $this->attemptRepository->updateReconciliationStatus(
                $attempt->id,
                'pending_review'
            )
        );

        // Confirm the database contains the expected value.
        $updatedAttempt = $this->attemptRepository
            ->findById($attempt->id);

        $this->assertNotNull($updatedAttempt);
        $this->assertSame(
            'pending_review',
            $updatedAttempt->reconciliationStatus
        );

        // A different valid transition must also succeed.
        $this->assertTrue(
            $this->attemptRepository->updateReconciliationStatus(
                $attempt->id,
                'refund_required'
            )
        );


        // Refund-required must not be downgraded.
        $this->assertFalse(
            $this->attemptRepository->updateReconciliationStatus(
                $attempt->id,
                'pending_review'
            )
        );

// Terminal states must not be reopened.
        $this->assertTrue(
            $this->attemptRepository->updateReconciliationStatus(
                $attempt->id,
                'refunded'
            )
        );

        $this->assertFalse(
            $this->attemptRepository->updateReconciliationStatus(
                $attempt->id,
                'refund_required'
            )
        );

        $this->assertTrue(
            $this->attemptRepository->updateReconciliationStatus(
                $attempt->id,
                'resolved'
            )
        );

        $this->assertFalse(
            $this->attemptRepository->updateReconciliationStatus(
                $attempt->id,
                'pending_review'
            )
        );

        // A nonexistent attempt must return false.
        $this->assertFalse(
            $this->attemptRepository->updateReconciliationStatus(
                PHP_INT_MAX,
                'pending_review'
            )
        );
    }

    public function testPaymentCompletionRecordsActivatedAttemptAtomically(): void
    {
        $attempt = $this->attemptRepository
            ->findByReference($this->reference);

        $this->assertNotNull($attempt);

        $this->db->beginTransaction();

        try {
            $this->assertTrue(
                $this->attemptRepository->updateStatus(
                    $attempt->id,
                    'succeeded'
                )
            );

            $this->assertTrue(
                $this->paymentRepository->completeWithActivatedAttempt(
                    $this->paymentId,
                    $attempt->id
                )
            );

            $payment = $this->paymentRepository
                ->findById($this->paymentId);

            $this->assertSame('completed', $payment->status);
            $this->assertSame(
                $attempt->id,
                $payment->activatedAttemptId
            );
            $this->assertNotNull($payment->paidAt);

            $attempt = $this->attemptRepository
                ->findByReference($this->reference);

            $this->assertNotNull($attempt);
            $this->assertSame(
                $attempt->id,
                $payment->activatedAttemptId
            );

            // The activation marker must not be replaceable.
            $this->assertFalse(
                $this->paymentRepository->completeWithActivatedAttempt(
                    $this->paymentId,
                    $attempt->id
                )
            );

        } finally {
            $this->db->rollBack();
        }
    }

    public function testSuccessfulPaymentActivatesSubscription(): void
    {
        $result = $this->subscriptionService
            ->verifySubscriptionPayment(
                $this->reference,
                $this->userId
            );

        // Verify the returned result.
        $this->assertSame('success', $result['status']);

        // Verify payment completion.
        $payment = $this->paymentRepository
            ->findById($this->paymentId);

        $this->assertNotNull($payment);
        $this->assertSame('completed', $payment->status);

        $this->assertNotNull($payment->paidAt);

        // Verify subscription activation.
        $subscription = $this->subscriptionRepository
            ->findById($this->subscriptionId);

        $this->assertNotNull($subscription);
        $this->assertSame('active', $subscription->status);

        $this->assertNotNull($subscription->startDate);
        $this->assertNotNull($subscription->endDate);

        // Verify billing period.
        $this->assertSame(
            'monthly',
            $subscription->billingPeriod
        );

        // Verify calendar-month expiry.
        $startDate = new \DateTimeImmutable(
            $subscription->startDate,
            new \DateTimeZone('UTC')
        );

        $nextMonth = $startDate->modify(
            'first day of next month'
        );

        $expectedEndDate = $nextMonth->modify(
            '+' . ($startDate->format('j') - 1) . ' days'
        );

        if (
            (int) $startDate->format('j')
            > (int) $nextMonth->format('t')
        ) {
            $expectedEndDate = $nextMonth->modify(
                'last day of this month'
            );
        }

        $this->assertSame(
            $expectedEndDate->format('Y-m-d H:i:s'),
            $subscription->endDate
        );

        // Verify exactly one activation event.
        $events = $this->eventRepository
            ->findBySubscriptionId($this->subscriptionId);

        $activationEvents = array_filter(
            $events,
            fn(array $event): bool =>
                $event['event_type'] === 'SUBSCRIPTION_ACTIVATED'
        );

        $this->assertCount(1, $activationEvents);
    }



    public function testLateSuccessfulPaymentAfterFailedAttemptActivatesSubscription(): void
    {
        $attempt = $this->attemptRepository
            ->findByReference($this->reference);

        $this->assertNotNull($attempt);

        // Simulate an attempt previously marked as failed.
        $this->assertTrue(
            $this->attemptRepository->updateStatus(
                $attempt->id,
                'failed',
                'Previous payment attempt was marked failed.'
            )
        );

        // Paystack now confirms that the transaction succeeded.
        $result = $this->subscriptionService
            ->verifySubscriptionPayment(
                $this->reference,
                $this->userId
            );

        $this->assertSame('success', $result['status']);

        // Verify the attempt was recovered.
        $updatedAttempt = $this->attemptRepository
            ->findByReference($this->reference);

        $this->assertSame('succeeded', $updatedAttempt->status);
        $this->assertNotNull($updatedAttempt->verifiedAt);

        // Verify payment completion.
        $payment = $this->paymentRepository
            ->findById($this->paymentId);

        $this->assertSame('completed', $payment->status);

        // Verify subscription activation.
        $subscription = $this->subscriptionRepository
            ->findById($this->subscriptionId);

        $this->assertSame('active', $subscription->status);

        // Exactly one activation event.
        $events = $this->eventRepository
            ->findBySubscriptionId($this->subscriptionId);

        $activationEvents = array_filter(
            $events,
            fn(array $event): bool =>
                $event['event_type'] === 'SUBSCRIPTION_ACTIVATED'
        );

        $this->assertCount(1, $activationEvents);
    }


    public function testRepeatedVerificationDoesNotDuplicateActivation(): void
    {
        // First verification activates the subscription.
        $firstResult = $this->subscriptionService
            ->verifySubscriptionPayment(
                $this->reference,
                $this->userId
            );

        $this->assertSame('success', $firstResult['status']);

        // Simulate another callback or page refresh.
        $paystackMock = $this->createMock(
            PaystackService::class
        );

        $paystackMock
            ->expects($this->once())
            ->method('verifyTransaction')
            ->with($this->reference)
            ->willReturn([
                'reference' => $this->reference,
                'status' => 'success',
                'currency' => 'GHS',
                'amount' => 2500,
            ]);

        $secondService = new SubscriptionService(
            $this->createMock(PlanRepository::class),
            $this->createMock(PlanVersionRepository::class),
            $this->subscriptionRepository,
            $this->paymentRepository,
            $this->createMock(UserRepository::class),
            $this->createMock(BarberRepository::class),
            $this->createMock(ShopRepository::class),
            $this->eventRepository,
            $this->attemptRepository,
            $paystackMock
        );

        $secondResult = $secondService
            ->verifySubscriptionPayment(
                $this->reference,
                $this->userId
            );

        $this->assertSame('success', $secondResult['status']);

        // Payment must remain completed.
        $payment = $this->paymentRepository
            ->findById($this->paymentId);

        $this->assertSame('completed', $payment->status);

        // The original attempt must remain the recorded activation attempt.
        $attempt = $this->attemptRepository
            ->findByReference($this->reference);

        $this->assertNotNull($attempt);
        $this->assertSame(
            $attempt->id,
            $payment->activatedAttemptId
        );

        // Subscription must remain active.
        $subscription = $this->subscriptionRepository
            ->findById($this->subscriptionId);

        $this->assertSame('active', $subscription->status);

        // Only one activation event must exist.
        $events = $this->eventRepository
            ->findBySubscriptionId($this->subscriptionId);

        $activationEvents = array_filter(
            $events,
            fn(array $event): bool =>
                $event['event_type'] === 'SUBSCRIPTION_ACTIVATED'
        );

        $this->assertCount(1, $activationEvents);

        // Repeated verification must not trigger reconciliation.
        $reconciliationEvents = array_filter(
            $events,
            fn(array $event): bool =>
                $event['event_type'] === 'SUBSCRIPTION_PAYMENT_RECONCILIATION_REQUIRED'
        );

        $this->assertCount(0, $reconciliationEvents);
    }



    public function testConcurrentVerificationActivatesSubscriptionOnlyOnce(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required.');
        }

        $worker = __DIR__ . '/SubscriptionVerificationWorker.php';

        $directory = sys_get_temp_dir()
            . '/hairconnect-subscription-'
            . bin2hex(random_bytes(8));

        if (!mkdir($directory, 0700)) {
            $this->fail('Unable to create temporary worker directory.');
        }

        $startFile = $directory . '/start';
        $resultA = $directory . '/result-a.json';
        $resultB = $directory . '/result-b.json';

        $pids = [];

        try {
            $pidA = pcntl_fork();

            if ($pidA === -1) {
                $this->fail('Unable to fork subscription worker A.');
            }

            if ($pidA === 0) {
                pcntl_exec(
                    PHP_BINARY,
                    [
                        $worker,
                        $this->reference,
                        (string) $this->userId,
                        $startFile,
                        $resultA,
                    ]
                );

                exit(127);
            }

            $pids[] = $pidA;

            $pidB = pcntl_fork();

            if ($pidB === -1) {
                $this->fail('Unable to fork subscription worker B.');
            }

            if ($pidB === 0) {
                pcntl_exec(
                    PHP_BINARY,
                    [
                        $worker,
                        $this->reference,
                        (string) $this->userId,
                        $startFile,
                        $resultB,
                    ]
                );

                exit(127);
            }

            $pids[] = $pidB;

            // Release both workers at approximately the same time.
            touch($startFile);

            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);

                $this->assertTrue(
                    pcntl_wifexited($status),
                    "Subscription worker {$pid} did not exit normally."
                );

                $this->assertSame(
                    0,
                    pcntl_wexitstatus($status),
                    "Subscription worker {$pid} failed."
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

            // Both requests should return successful results.
            $this->assertTrue(
                $dataA['success'],
                'Worker A failed: ' . ($dataA['error'] ?? 'Unknown error')
            );

            $this->assertTrue(
                $dataB['success'],
                'Worker B failed: ' . ($dataB['error'] ?? 'Unknown error')
            );

            $this->assertSame('success', $dataA['status']);
            $this->assertSame('success', $dataB['status']);

            // Verify the final payment state.
            $payment = $this->paymentRepository
                ->findById($this->paymentId);

            $this->assertSame('completed', $payment->status);
            $this->assertNotNull($payment->paidAt);

            // Verify the subscription was activated.
            $subscription = $this->subscriptionRepository
                ->findById($this->subscriptionId);

            $this->assertSame('active', $subscription->status);
            $this->assertNotNull($subscription->startDate);
            $this->assertNotNull($subscription->endDate);

            // Exactly one activation event must exist.
            $events = $this->eventRepository
                ->findBySubscriptionId($this->subscriptionId);

            $this->assertCount(
                1,
                $events,
                'Concurrent verification must create only one activation event.'
            );

        } finally {
            // Ensure no worker remains running.
            if (file_exists($startFile)) {
                touch($startFile);
            }

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

            rmdir($directory);
        }
    }



    public function testCompletedPaymentWithPendingSubscriptionRequiresReconciliation(): void
    {
        // Simulate an inconsistent database state.
        $this->paymentRepository->updateStatus(
            $this->paymentId,
            'completed'
        );

        $result = $this->subscriptionService
            ->verifySubscriptionPayment(
                $this->reference,
                $this->userId
            );

        // Verification must return a reconciliation result.
        $this->assertSame(
            'reconciliation_required',
            $result['status']
        );

        $this->assertSame(
            'pending_review',
            $result['reconciliation_status']
        );

        // Payment must remain completed.
        $payment = $this->paymentRepository
            ->findById($this->paymentId);

        $this->assertSame('completed', $payment->status);
        $this->assertNotNull($payment->paidAt);

        // Subscription must remain pending.
        $subscription = $this->subscriptionRepository
            ->findById($this->subscriptionId);

        $this->assertSame('pending', $subscription->status);
        $this->assertNull($subscription->startDate);
        $this->assertNull($subscription->endDate);

        // Attempt must be marked successful.
        $attempt = $this->attemptRepository
            ->findByReference($this->reference);

        $this->assertNotNull($attempt);
        $this->assertSame('succeeded', $attempt->status);
        $this->assertSame(
            'pending_review',
            $attempt->reconciliationStatus
        );

        // A reconciliation event must exist.
        $events = $this->eventRepository
            ->findBySubscriptionId($this->subscriptionId);

        $reconciliationEvents = array_filter(
            $events,
            fn(array $event): bool =>
                $event['event_type']
                === 'SUBSCRIPTION_PAYMENT_RECONCILIATION_REQUIRED'
        );

        $this->assertCount(1, $reconciliationEvents);

        // No activation event must exist.
        $activationEvents = array_filter(
            $events,
            fn(array $event): bool =>
                $event['event_type'] === 'SUBSCRIPTION_ACTIVATED'
        );

        $this->assertCount(0, $activationEvents);
    }



    public function testFailedPaystackPaymentDoesNotActivateSubscription(): void
    {
        $this->paystackResponse['status'] = 'failed';

        try {
            $this->subscriptionService
                ->verifySubscriptionPayment(
                    $this->reference,
                    $this->userId
                );

            $this->fail(
                'Failed Paystack transactions must be rejected.'
            );

        } catch (\Exception $exception) {
            $this->assertSame(
                'Payment was not successful.',
                $exception->getMessage()
            );
        }

        // Payment must remain pending.
        $payment = $this->paymentRepository
            ->findById($this->paymentId);

        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->paidAt);

        // Subscription must remain pending.
        $subscription = $this->subscriptionRepository
            ->findById($this->subscriptionId);

        $this->assertSame('pending', $subscription->status);
        $this->assertNull($subscription->startDate);
        $this->assertNull($subscription->endDate);

        // No activation event should exist.
        $events = $this->eventRepository
            ->findBySubscriptionId($this->subscriptionId);

        $this->assertCount(0, $events);
    }


    #[DataProvider('invalidPaystackStatuses')]
    public function testNonSuccessfulPaystackStatusesDoNotActivateSubscription(
        array $transaction
    ): void {
        $this->paystackResponse = $transaction;
        $this->paystackResponse['reference'] = $this->reference;

        $caughtException = null;

        try {
            $this->subscriptionService
                ->verifySubscriptionPayment(
                    $this->reference,
                    $this->userId
                );
        } catch (\Exception $exception) {
            $caughtException = $exception;
        }

        $this->assertNotNull(
            $caughtException,
            'Non-successful Paystack transactions must be rejected.'
        );

        $this->assertSame(
            'Payment was not successful.',
            $caughtException->getMessage()
        );

        $payment = $this->paymentRepository
            ->findById($this->paymentId);

        $subscription = $this->subscriptionRepository
            ->findById($this->subscriptionId);

        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->paidAt);

        $this->assertSame('pending', $subscription->status);
        $this->assertNull($subscription->startDate);
        $this->assertNull($subscription->endDate);

        $this->assertCount(
            0,
            $this->eventRepository
                ->findBySubscriptionId($this->subscriptionId)
        );
    }

    public static function invalidPaystackStatuses(): array
    {
        return [
            'pending status' => [
                ['reference' => 'PLACEHOLDER', 'status' => 'pending', 'currency' => 'GHS', 'amount' => 2500],
            ],
            'abandoned status' => [
                ['reference' => 'PLACEHOLDER', 'status' => 'abandoned', 'currency' => 'GHS', 'amount' => 2500],
            ],
            'failed status' => [
                ['reference' => 'PLACEHOLDER', 'status' => 'failed', 'currency' => 'GHS', 'amount' => 2500],
            ],
            'reversed status' => [
                ['reference' => 'PLACEHOLDER', 'status' => 'reversed', 'currency' => 'GHS', 'amount' => 2500],
            ],
            'processing status' => [
                ['reference' => 'PLACEHOLDER', 'status' => 'processing', 'currency' => 'GHS', 'amount' => 2500],
            ],
            'empty status' => [
                ['reference' => 'PLACEHOLDER', 'status' => '', 'currency' => 'GHS', 'amount' => 2500],
            ],
            'missing status' => [
                ['reference' => 'PLACEHOLDER', 'currency' => 'GHS', 'amount' => 2500],
            ],
        ];
    }



    public function testIncorrectPaymentAmountIsRejected(): void
    {
        $this->paystackResponse['amount'] = 2000;

        try {
            $this->subscriptionService
                ->verifySubscriptionPayment(
                    $this->reference,
                    $this->userId
                );

            $this->fail('Incorrect payment amount must be rejected.');
        } catch (\Exception $exception) {
            $this->assertSame(
                'Payment amount does not match the subscription amount.',
                $exception->getMessage()
            );
        }

        $payment = $this->paymentRepository
            ->findById($this->paymentId);

        $subscription = $this->subscriptionRepository
            ->findById($this->subscriptionId);

        $this->assertSame('pending', $payment->status);
        $this->assertSame('pending', $subscription->status);
        $this->assertNull($payment->paidAt);
        $this->assertNull($subscription->startDate);
        $this->assertNull($subscription->endDate);

        $this->assertCount(
            0,
            $this->eventRepository
                ->findBySubscriptionId($this->subscriptionId)
        );
    }

    public function testIncorrectPaymentCurrencyIsRejected(): void
    {
        $this->paystackResponse['currency'] = 'USD';

        try {
            $this->subscriptionService
                ->verifySubscriptionPayment(
                    $this->reference,
                    $this->userId
                );

            $this->fail('Incorrect payment currency must be rejected.');
        } catch (\Exception $exception) {
            $this->assertSame(
                'Payment currency does not match the subscription payment.',
                $exception->getMessage()
            );
        }

        $payment = $this->paymentRepository
            ->findById($this->paymentId);

        $subscription = $this->subscriptionRepository
            ->findById($this->subscriptionId);

        $this->assertSame('pending', $payment->status);
        $this->assertSame('pending', $subscription->status);
        $this->assertNull($payment->paidAt);
        $this->assertNull($subscription->startDate);
        $this->assertNull($subscription->endDate);

        $this->assertCount(
            0,
            $this->eventRepository
                ->findBySubscriptionId($this->subscriptionId)
        );
    }


    public function testUserCannotVerifyAnotherUsersSubscriptionPayment(): void
    {
        // Create a Paystack mock that must never be called.
        $paystackMock = $this->createMock(
            PaystackService::class
        );

        $paystackMock
            ->expects($this->never())
            ->method('verifyTransaction');

        $service = new SubscriptionService(
            $this->createMock(PlanRepository::class),
            $this->createMock(PlanVersionRepository::class),
            $this->subscriptionRepository,
            $this->paymentRepository,
            $this->createMock(UserRepository::class),
            $this->createMock(BarberRepository::class),
            $this->createMock(ShopRepository::class),
            $this->eventRepository,
            $this->attemptRepository,
            $paystackMock
        );

        try {
            $service->verifySubscriptionPayment(
                $this->reference,
                $this->userId + 1
            );

            $this->fail(
                'A user must not verify another user\'s payment.'
            );
        } catch (\Exception $exception) {
            $this->assertSame(
                'You are not authorized to verify this payment.',
                $exception->getMessage()
            );
        }

        // Payment must remain pending.
        $payment = $this->paymentRepository
            ->findById($this->paymentId);

        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->paidAt);

        // Subscription must remain pending.
        $subscription = $this->subscriptionRepository
            ->findById($this->subscriptionId);

        $this->assertSame('pending', $subscription->status);
        $this->assertNull($subscription->startDate);
        $this->assertNull($subscription->endDate);

        // No activation event should exist.
        $this->assertCount(
            0,
            $this->eventRepository
                ->findBySubscriptionId($this->subscriptionId)
        );
    }


    public function testEventCreationFailureRollsBackPaymentAndSubscription(): void
    {
        // Simulate failure when recording the activation event.
        $eventRepositoryMock = $this->createMock(
            SubscriptionEventRepository::class
        );

        $eventRepositoryMock
            ->expects($this->once())
            ->method('create')
            ->willReturn(false);

        // Simulate a successful Paystack verification.
        $paystackMock = $this->createStub(
            PaystackService::class
        );

        $paystackMock
            ->method('verifyTransaction')
            ->willReturn([
                'reference' => $this->reference,
                'status' => 'success',
                'currency' => 'GHS',
                'amount' => 2500,
            ]);

        // Inject the failing event repository.
        $service = new SubscriptionService(
            $this->createMock(PlanRepository::class),
            $this->createMock(PlanVersionRepository::class),
            $this->subscriptionRepository,
            $this->paymentRepository,
            $this->createMock(UserRepository::class),
            $this->createMock(BarberRepository::class),
            $this->createMock(ShopRepository::class),
            $eventRepositoryMock,
            $this->attemptRepository,
            $paystackMock
        );

        try {
            $service->verifySubscriptionPayment(
                $this->reference,
                $this->userId
            );

            $this->fail(
                'Verification must fail when activation event creation fails.'
            );
        } catch (\Exception $exception) {
            $this->assertSame(
                'Unable to record subscription activation event.',
                $exception->getMessage()
            );
        }

        // Payment completion must have been rolled back.
        $payment = $this->paymentRepository
            ->findById($this->paymentId);

        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->paidAt);

        // Subscription activation must have been rolled back.
        $subscription = $this->subscriptionRepository
            ->findById($this->subscriptionId);

        $this->assertSame('pending', $subscription->status);
        $this->assertNull($subscription->startDate);
        $this->assertNull($subscription->endDate);

        // No activation event should exist in the real database.
        $events = $this->eventRepository
            ->findBySubscriptionId($this->subscriptionId);

        $this->assertCount(0, $events);
    }



    public function testMismatchedPaystackReferenceIsRejected(): void
    {
        $this->paystackResponse['reference'] = 'WRONG_REFERENCE';

        try {
            $this->subscriptionService
                ->verifySubscriptionPayment(
                    $this->reference,
                    $this->userId
                );

            $this->fail(
                'A mismatched Paystack reference must be rejected.'
            );
        } catch (\Exception $exception) {
            $this->assertSame(
                'Paystack transaction reference does not match the payment.',
                $exception->getMessage()
            );
        }

        $payment = $this->paymentRepository
            ->findById($this->paymentId);

        $subscription = $this->subscriptionRepository
            ->findById($this->subscriptionId);

        $this->assertSame('pending', $payment->status);
        $this->assertSame('pending', $subscription->status);
        $this->assertNull($payment->paidAt);
        $this->assertNull($subscription->startDate);
        $this->assertNull($subscription->endDate);

        $this->assertCount(
            0,
            $this->eventRepository
                ->findBySubscriptionId($this->subscriptionId)
        );
    }


    #[DataProvider('invalidPaymentRepositoryStatuses')]
    public function testRepositoryRejectsInvalidPaymentStatuses(
        string $status
    ): void {
        $caughtException = null;

        try {
            $this->paymentRepository->updateStatus(
                $this->paymentId,
                $status
            );
        } catch (\InvalidArgumentException $exception) {
            $caughtException = $exception;
        }

        $this->assertNotNull(
            $caughtException,
            'Invalid payment statuses must be rejected.'
        );

        $this->assertSame(
            'Invalid subscription payment status.',
            $caughtException->getMessage()
        );

        // Confirm the database record was not modified.
        $payment = $this->paymentRepository
            ->findById($this->paymentId);

        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->paidAt);
    }

    public static function invalidPaymentRepositoryStatuses(): array
    {
        return [
            'successful' => ['successful'],
            'processing' => ['processing'],
            'refunded' => ['refunded'],
            'abandoned' => ['abandoned'],
            'unknown status' => ['anything'],
        ];
    }


    #[DataProvider('invalidSubscriptionRepositoryStatuses')]
    public function testRepositoryRejectsInvalidSubscriptionStatuses(
        string $status
    ): void {
        $caughtException = null;

        try {
            $this->subscriptionRepository->updateStatus(
                $this->subscriptionId,
                $status
            );
        } catch (\InvalidArgumentException $exception) {
            $caughtException = $exception;
        }

        $this->assertNotNull(
            $caughtException,
            'Invalid subscription statuses must be rejected.'
        );

        $this->assertSame(
            'Invalid subscription status.',
            $caughtException->getMessage()
        );

        // Confirm the database record was not modified.
        $subscription = $this->subscriptionRepository
            ->findById($this->subscriptionId);

        $this->assertSame('pending', $subscription->status);
    }

    public static function invalidSubscriptionRepositoryStatuses(): array
    {
        return [
            'processing' => ['processing'],
            'completed' => ['completed'],
            'successful' => ['successful'],
            'refunded' => ['refunded'],
            'unknown status' => ['anything'],
        ];
    }

    #[DataProvider('validSubscriptionRepositoryStatuses')]
    public function testRepositoryAcceptsValidSubscriptionStatuses(
        string $status
    ): void {
        // Ensure the current status differs from the requested status.
        $initialStatus = $status === 'suspended'
            ? 'active'
            : 'suspended';

        $this->subscriptionRepository->updateStatus(
            $this->subscriptionId,
            $initialStatus
        );

        $updated = $this->subscriptionRepository->updateStatus(
            $this->subscriptionId,
            $status
        );

        $this->assertTrue($updated);

        $subscription = $this->subscriptionRepository
            ->findById($this->subscriptionId);

        $this->assertSame($status, $subscription->status);
    }

    public static function validSubscriptionRepositoryStatuses(): array
    {
        return [
            'pending' => ['pending'],
            'active' => ['active'],
            'expired' => ['expired'],
            'cancelled' => ['cancelled'],
            'suspended' => ['suspended'],
        ];
    }

    public function testRepositoryReturnsFalseForNonexistentSubscription(): void
    {
        $updated = $this->subscriptionRepository->updateStatus(
            PHP_INT_MAX,
            'active'
        );

        $this->assertFalse($updated);
    }

    public function testSecondSuccessfulAttemptRequiresRefundReconciliation(): void
    {
        // First attempt succeeds and activates the subscription.
        $firstResult = $this->subscriptionService
            ->verifySubscriptionPayment(
                $this->reference,
                $this->userId
            );

        $this->assertSame('success', $firstResult['status']);

        // Create a second payment attempt for the same parent payment.
        $secondReference = 'TEST_SUB_' . bin2hex(random_bytes(12));

        $secondAttempt = $this->attemptRepository->create(
            $this->paymentId,
            2,
            $secondReference
        );

        $this->assertNotNull($secondAttempt);

        // Paystack confirms that the second checkout was also paid.
        $this->paystackResponse = [
            'reference' => $secondReference,
            'status' => 'success',
            'currency' => 'GHS',
            'amount' => 2500,
        ];

        $secondResult = $this->subscriptionService
            ->verifySubscriptionPayment(
                $secondReference,
                $this->userId
            );

        // The second payment must require reconciliation.
        $this->assertSame(
            'reconciliation_required',
            $secondResult['status']
        );

        $this->assertSame(
            'refund_required',
            $secondResult['reconciliation_status']
        );

        // Parent payment remains completed.
        $payment = $this->paymentRepository
            ->findById($this->paymentId);

        $this->assertSame('completed', $payment->status);

        // Subscription must remain active.
        $subscription = $this->subscriptionRepository
            ->findById($this->subscriptionId);

        $this->assertSame('active', $subscription->status);

        // Second attempt must be marked successful.
        $updatedAttempt = $this->attemptRepository
            ->findByReference($secondReference);

        $this->assertSame('succeeded', $updatedAttempt->status);
        $this->assertSame(
            'refund_required',
            $updatedAttempt->reconciliationStatus
        );

        // Exactly one activation event.
        $events = $this->eventRepository
            ->findBySubscriptionId($this->subscriptionId);

        $activationEvents = array_filter(
            $events,
            fn(array $event): bool =>
                $event['event_type'] === 'SUBSCRIPTION_ACTIVATED'
        );

        $this->assertCount(1, $activationEvents);

        // Exactly one reconciliation event for the second attempt.
        $reconciliationEvents = array_filter(
            $events,
            fn(array $event): bool =>
                $event['event_type']
                === 'SUBSCRIPTION_PAYMENT_RECONCILIATION_REQUIRED'
        );

        $this->assertCount(1, $reconciliationEvents);
    }


    public function testRepeatedDuplicatePaymentVerificationDoesNotDuplicateReconciliationEvent(): void
    {
        // First attempt activates the subscription.
        $this->subscriptionService->verifySubscriptionPayment(
            $this->reference,
            $this->userId
        );

        // Create second attempt.
        $secondReference = 'TEST_SUB_' . bin2hex(random_bytes(12));

        $secondAttempt = $this->attemptRepository->create(
            $this->paymentId,
            2,
            $secondReference
        );

        $this->assertNotNull($secondAttempt);

        $this->paystackResponse = [
            'reference' => $secondReference,
            'status' => 'success',
            'currency' => 'GHS',
            'amount' => 2500,
        ];

        // First verification of second attempt.
        $firstResult = $this->subscriptionService
            ->verifySubscriptionPayment(
                $secondReference,
                $this->userId
            );

        $this->assertSame(
            'reconciliation_required',
            $firstResult['status']
        );

        // Repeat the same callback.
        $secondResult = $this->subscriptionService
            ->verifySubscriptionPayment(
                $secondReference,
                $this->userId
            );

        $this->assertSame(
            'reconciliation_required',
            $secondResult['status']
        );

        $this->assertSame(
            'refund_required',
            $secondResult['reconciliation_status']
        );

        // Only one activation event.
        $events = $this->eventRepository
            ->findBySubscriptionId($this->subscriptionId);

        $activationEvents = array_filter(
            $events,
            fn(array $event): bool =>
                $event['event_type'] === 'SUBSCRIPTION_ACTIVATED'
        );

        $this->assertCount(1, $activationEvents);

        // Only one reconciliation event.
        $reconciliationEvents = array_filter(
            $events,
            fn(array $event): bool =>
                $event['event_type']
                === 'SUBSCRIPTION_PAYMENT_RECONCILIATION_REQUIRED'
        );

        $this->assertCount(1, $reconciliationEvents);
    }



    public function testPendingReviewEscalatesToRefundRequired(): void
    {
        // First payment activates the subscription.
        $firstResult = $this->subscriptionService
            ->verifySubscriptionPayment(
                $this->reference,
                $this->userId
            );

        $this->assertSame('success', $firstResult['status']);

        // Create a second payment attempt.
        $secondReference = 'TEST_SUB_' . bin2hex(random_bytes(12));

        $secondAttempt = $this->attemptRepository->create(
            $this->paymentId,
            2,
            $secondReference
        );

        $this->assertNotNull($secondAttempt);

        // Simulate an earlier manual reconciliation review.
        $this->assertTrue(
            $this->attemptRepository->updateReconciliationStatus(
                $secondAttempt->id,
                'pending_review'
            )
        );

        $this->assertTrue(
            $this->eventRepository->create(
                $this->subscriptionId,
                $this->planVersionId,
                $this->userId,
                'SUBSCRIPTION_PAYMENT_RECONCILIATION_REQUIRED',
                'Second payment attempt requires manual review.',
                $this->userId
            )
        );

        // Paystack confirms the second payment succeeded.
        $this->paystackResponse = [
            'reference' => $secondReference,
            'status' => 'success',
            'currency' => 'GHS',
            'amount' => 2500,
        ];

        $result = $this->subscriptionService
            ->verifySubscriptionPayment(
                $secondReference,
                $this->userId
            );

        // Reconciliation must be escalated.
        $this->assertSame(
            'reconciliation_required',
            $result['status']
        );

        $this->assertSame(
            'refund_required',
            $result['reconciliation_status']
        );

        // Verify persisted attempt state.
        $updatedAttempt = $this->attemptRepository
            ->findByReference($secondReference);

        $this->assertSame('succeeded', $updatedAttempt->status);

        $this->assertSame(
            'refund_required',
            $updatedAttempt->reconciliationStatus
        );

        // Subscription must remain active, not activated again.
        $subscription = $this->subscriptionRepository
            ->findById($this->subscriptionId);

        $this->assertSame('active', $subscription->status);

        // Exactly one activation event.
        $events = $this->eventRepository
            ->findBySubscriptionId($this->subscriptionId);

        $activationEvents = array_filter(
            $events,
            fn(array $event): bool =>
                $event['event_type'] === 'SUBSCRIPTION_ACTIVATED'
        );

        $this->assertCount(1, $activationEvents);

        // Two reconciliation events: initial review and escalation.
        $reconciliationEvents = array_filter(
            $events,
            fn(array $event): bool =>
                $event['event_type']
                === 'SUBSCRIPTION_PAYMENT_RECONCILIATION_REQUIRED'
        );

        $this->assertCount(2, $reconciliationEvents);
    }

}
