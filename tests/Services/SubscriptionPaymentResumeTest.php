<?php

declare(strict_types=1);

use App\Helpers\DatabaseManager;
use App\Models\Barber;
use App\Models\User;
use App\Repositories\BarberRepository;
use App\Repositories\PlanRepository;
use App\Repositories\PlanVersionRepository;
use App\Repositories\ShopRepository;
use App\Repositories\SubscriptionEventRepository;
use App\Repositories\SubscriptionPaymentAttemptRepository;
use App\Repositories\SubscriptionPaymentRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\UserRepository;
use App\Services\PaystackService;
use App\Services\SubscriptionService;
use PHPUnit\Framework\TestCase;

final class SubscriptionPaymentResumeTest extends TestCase
{
    private PDO $db;

    private SubscriptionRepository $subscriptionRepository;
    private SubscriptionPaymentRepository $paymentRepository;
    private SubscriptionPaymentAttemptRepository $attemptRepository;
    private SubscriptionEventRepository $eventRepository;

    private UserRepository $userRepository;
    private BarberRepository $barberRepository;
    private ShopRepository $shopRepository;

    private int $userId;
    private int $beneficiaryId;

    private array $createdSubscriptionIds = [];
    private array $createdPlanVersionIds = [];
    private array $createdPlanIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        DatabaseManager::resetConnection('subscription_db');

        $this->db = DatabaseManager::getConnection(
            'subscription_db'
        );

        $databaseName = $this->db
            ->query('SELECT DATABASE()')
            ->fetchColumn();

        $this->assertSame(
            'subscription_test_db',
            $databaseName,
            'Tests must never write to the production database.'
        );

        $this->db->exec("SET time_zone = '+00:00'");

        $this->subscriptionRepository = new SubscriptionRepository();
        $this->paymentRepository = new SubscriptionPaymentRepository();
        $this->attemptRepository = new SubscriptionPaymentAttemptRepository();
        $this->eventRepository = new SubscriptionEventRepository();

        $this->userId = random_int(100000000, 999999999);
        $this->beneficiaryId = random_int(100000000, 999999999);

        $this->userRepository = $this->createMock(UserRepository::class);
        $this->barberRepository = $this->createMock(BarberRepository::class);
        $this->shopRepository = $this->createMock(ShopRepository::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdSubscriptionIds as $id) {
            $stmt = $this->db->prepare("
                DELETE FROM subscription_events
                WHERE subscription_id = :id
            ");
            $stmt->execute(['id' => $id]);

            $stmt = $this->db->prepare("
                DELETE spa
                FROM subscription_payment_attempts spa
                INNER JOIN subscription_payments sp
                    ON sp.id = spa.payment_id
                WHERE sp.subscription_id = :id
            ");
            $stmt->execute(['id' => $id]);

            $stmt = $this->db->prepare("
                DELETE FROM subscription_payments
                WHERE subscription_id = :id
            ");
            $stmt->execute(['id' => $id]);

            $stmt = $this->db->prepare("
                DELETE FROM subscriptions
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id]);
        }

        foreach ($this->createdPlanVersionIds as $id) {
            $stmt = $this->db->prepare("
                DELETE FROM plan_versions
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id]);
        }

        foreach ($this->createdPlanIds as $id) {
            $stmt = $this->db->prepare("
                DELETE FROM plans
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id]);
        }

        parent::tearDown();
    }

    private function createPlan(): int
    {
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
            )
        ");

        $stmt->execute([
            'name' => 'Resume Test ' . bin2hex(random_bytes(4)),
            'features' => json_encode([
                'appointments' => true,
            ]),
        ]);

        $planId = (int) $this->db->lastInsertId();

        $this->createdPlanIds[] = $planId;

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
            'plan_id' => $planId,
            'proposed_by' => $this->userId,
        ]);

        $this->createdPlanVersionIds[] =
            (int) $this->db->lastInsertId();

        return $planId;
    }

    private function configureUser(): void
    {
        $user = new User([
            'id' => $this->userId,
            'public_id' => bin2hex(random_bytes(16)),
            'first_name' => 'Test',
            'last_name' => 'Subscriber',
            'email' => 'subscriber@example.com',
            'phone' => '0200000000',
            'password_hash' => 'test-hash',
            'status' => 'active',
        ]);

        $this->userRepository
            ->method('findById')
            ->with($this->userId)
            ->willReturn($user);

        $this->userRepository
            ->method('getRoles')
            ->with($this->userId)
            ->willReturn(['BARBER']);

        $this->barberRepository
            ->method('findByUserId')
            ->with($this->userId)
            ->willReturn(new Barber([
                'id' => $this->beneficiaryId,
                'user_id' => $this->userId,
                'shop_id' => null,
                'approval_status' => 'approved',
                'status' => 'active',
            ]));
    }

    private function createService(
        PaystackService $paystack
    ): SubscriptionService {
        return new SubscriptionService(
            new PlanRepository(),
            new PlanVersionRepository(),
            $this->subscriptionRepository,
            $this->paymentRepository,
            $this->userRepository,
            $this->barberRepository,
            $this->shopRepository,
            $this->eventRepository,
            $this->attemptRepository,
            $paystack
        );
    }

    public function testResumeReusesExistingPendingCheckout(): void
    {
        $this->configureUser();

        $planId = $this->createPlan();

        $reference = 'TEST_RESUME_REUSE_' .
            bin2hex(random_bytes(8));

        $authorizationUrl = 'https://example.test/pay';

        $paystack = $this->createMock(PaystackService::class);

        // These should be called during initial subscription creation only.
        $paystack
            ->expects($this->once())
            ->method('generateReference')
            ->willReturn($reference);

        $paystack
            ->expects($this->once())
            ->method('initializeTransaction')
            ->willReturn([
                'reference' => $reference,
                'authorization_url' => $authorizationUrl,
                'access_code' => 'test-access-code',
            ]);

        $service = $this->createService($paystack);

        // Create the initial pending subscription and checkout.
        $creationResult = $service->createSubscription(
            $this->userId,
            $planId,
            'monthly'
        );

        $subscription = $creationResult['subscription'];

        $this->createdSubscriptionIds[] = $subscription->id;

        $payment = $creationResult['payment'];

        // Confirm initial creation produced exactly one attempt.
        $initialAttempts = $this->attemptRepository
            ->findByPaymentId($payment->id);

        $this->assertCount(1, $initialAttempts);

        $initialAttempt = $initialAttempts[0];

        $this->assertSame('pending', $initialAttempt->status);
        $this->assertSame($reference, $initialAttempt->reference);

        // Resume must reuse the existing checkout.
        // No second Paystack initialization should occur.
        $result = $service->resumePendingSubscriptionPayment(
            $this->userId
        );

        $this->assertSame($reference, $result['reference']);

        $this->assertSame(
            $authorizationUrl,
            $result['authorization_url']
        );

        $this->assertSame(
            'test-access-code',
            $result['access_code']
        );

        $this->assertSame('pending', $result['payment']->status);
        $this->assertSame($reference, $result['payment']->reference);

        $this->assertSame(
            $subscription->id,
            $result['subscription']->id
        );

        // Verify no duplicate attempt was created.
        $finalAttempts = $this->attemptRepository
            ->findByPaymentId($payment->id);

        $this->assertCount(1, $finalAttempts);

        $this->assertSame(
            $initialAttempt->id,
            $finalAttempts[0]->id
        );
    }


    public function testUnknownInitializationOutcomeBlocksAnotherCheckout(): void
    {
        $this->configureUser();

        $planId = $this->createPlan();

        $reference = 'TEST_RESUME_UNKNOWN_' .
            bin2hex(random_bytes(8));

        $paystack = $this->createMock(PaystackService::class);

        $paystack
            ->expects($this->once())
            ->method('generateReference')
            ->willReturn($reference);

        $paystack
            ->expects($this->once())
            ->method('initializeTransaction')
            ->willReturn([
                'reference' => $reference,
                'authorization_url' => 'https://example.test/pay',
                'access_code' => 'test-access-code',
            ]);

        $service = $this->createService($paystack);

        // Create the initial subscription and checkout.
        $creationResult = $service->createSubscription(
            $this->userId,
            $planId,
            'monthly'
        );

        $subscription = $creationResult['subscription'];

        $this->createdSubscriptionIds[] = $subscription->id;

        $payment = $creationResult['payment'];

        $attempts = $this->attemptRepository
            ->findByPaymentId($payment->id);

        $this->assertCount(1, $attempts);

        // Simulate an attempt left unresolved after a provider timeout.
// Direct SQL is intentional here: this establishes a test fixture
// without violating the repository's permitted state transitions.
        $stmt = $this->db->prepare("
    UPDATE subscription_payment_attempts
    SET
        status = 'initialization_unknown',
        failure_reason = :failure_reason
    WHERE id = :id
");

        $stmt->execute([
            'failure_reason' => 'Simulated uncertain provider response.',
            'id' => $attempts[0]->id,
        ]);

        $this->assertSame(1, $stmt->rowCount());


        // Resume must be rejected before another Paystack request.
        $exception = null;

        try {
            $service->resumePendingSubscriptionPayment(
                $this->userId
            );
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNotNull(
            $exception,
            'An unknown initialization outcome must block another checkout.'
        );

        $this->assertStringContainsString(
            'Manual reconciliation is required',
            $exception->getMessage()
        );

        // Verify the attempt remains unresolved.
        $updatedAttempts = $this->attemptRepository
            ->findByPaymentId($payment->id);

        $this->assertCount(1, $updatedAttempts);

        $this->assertSame(
            'initialization_unknown',
            $updatedAttempts[0]->status
        );

        // Parent payment and subscription must remain pending.
        $updatedPayment = $this->paymentRepository
            ->findById($payment->id);

        $updatedSubscription = $this->subscriptionRepository
            ->findById($subscription->id);

        $this->assertSame('pending', $updatedPayment->status);
        $this->assertSame('pending', $updatedSubscription->status);

        // Original parent reference must remain unchanged.
        $this->assertSame(
            $reference,
            $updatedPayment->reference
        );
    }


    public function testDefinitiveInitializationFailureDuringResumePreservesPendingSubscription(): void
    {
        $this->configureUser();

        $planId = $this->createPlan();

        $initialReference = 'TEST_RESUME_INITIAL_' .
            bin2hex(random_bytes(8));

        $retryReference = 'TEST_RESUME_RETRY_' .
            bin2hex(random_bytes(8));

        $paystack = $this->createMock(PaystackService::class);

        $paystack
            ->expects($this->exactly(2))
            ->method('generateReference')
            ->willReturnOnConsecutiveCalls(
                $initialReference,
                $retryReference
            );

        $paystack
            ->expects($this->exactly(2))
            ->method('initializeTransaction')
            ->willReturnOnConsecutiveCalls(
                [
                    'reference' => $initialReference,
                    'authorization_url' => 'https://example.test/initial',
                    'access_code' => 'initial-access-code',
                ],
                $this->throwException(
                    new \App\Exceptions\PaystackInitializationException(
                        'Paystack rejected the retry.',
                        false
                    )
                )
            );

        $service = $this->createService($paystack);

        // Create the initial subscription and checkout.
        $creationResult = $service->createSubscription(
            $this->userId,
            $planId,
            'monthly'
        );

        $subscription = $creationResult['subscription'];
        $this->createdSubscriptionIds[] = $subscription->id;

        $payment = $creationResult['payment'];

        // Confirm the initial attempt exists.
        $initialAttempts = $this->attemptRepository
            ->findByPaymentId($payment->id);

        $this->assertCount(1, $initialAttempts);
        $this->assertSame('pending', $initialAttempts[0]->status);

        // Simulate a definitive failure of the previous checkout.
        $this->assertTrue(
            $this->attemptRepository->updateStatus(
                $initialAttempts[0]->id,
                'failed',
                'Previous checkout failed.'
            )
        );

        // Resume should create a second attempt and fail definitively.
        $exception = null;

        try {
            $service->resumePendingSubscriptionPayment(
                $this->userId
            );
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNotNull(
            $exception,
            'A definitive Paystack initialization failure must be reported.'
        );

        // Verify both attempts exist and have the expected states.
        $attempts = $this->attemptRepository
            ->findByPaymentId($payment->id);

        $this->assertCount(2, $attempts);

        $this->assertSame('failed', $attempts[0]->status);
        $this->assertSame('failed', $attempts[1]->status);

        $this->assertSame(1, $attempts[0]->attemptNumber);
        $this->assertSame(2, $attempts[1]->attemptNumber);

        $this->assertSame(
            $retryReference,
            $attempts[1]->reference
        );

        // Parent payment must remain pending.
        $updatedPayment = $this->paymentRepository
            ->findById($payment->id);

        $this->assertSame('pending', $updatedPayment->status);

        // Subscription must remain pending.
        $updatedSubscription = $this->subscriptionRepository
            ->findById($subscription->id);

        $this->assertSame('pending', $updatedSubscription->status);

        // The original parent reference must never be overwritten.
        $this->assertSame(
            $initialReference,
            $updatedPayment->reference
        );
    }

    public function testSequentialRetriesIncrementAttemptNumbersCorrectly(): void
    {
        $this->configureUser();

        $planId = $this->createPlan();

        $initialReference = 'TEST_SEQUENCE_INITIAL_' .
            bin2hex(random_bytes(8));

        $firstRetryReference = 'TEST_SEQUENCE_RETRY_2_' .
            bin2hex(random_bytes(8));

        $secondRetryReference = 'TEST_SEQUENCE_RETRY_3_' .
            bin2hex(random_bytes(8));

        $paystack = $this->createMock(PaystackService::class);

        $paystack
            ->expects($this->exactly(3))
            ->method('generateReference')
            ->willReturnOnConsecutiveCalls(
                $initialReference,
                $firstRetryReference,
                $secondRetryReference
            );

        $paystack
            ->expects($this->exactly(3))
            ->method('initializeTransaction')
            ->willReturnOnConsecutiveCalls(
                [
                    'reference' => $initialReference,
                    'authorization_url' => 'https://example.test/initial',
                    'access_code' => 'initial-access-code',
                ],
                $this->throwException(
                    new \App\Exceptions\PaystackInitializationException(
                        'First retry was rejected.',
                        false
                    )
                ),
                [
                    'reference' => $secondRetryReference,
                    'authorization_url' => 'https://example.test/retry-3',
                    'access_code' => 'retry-3-access-code',
                ]
            );

        $service = $this->createService($paystack);

        // Initial subscription creation produces attempt #1.
        $creationResult = $service->createSubscription(
            $this->userId,
            $planId,
            'monthly'
        );

        $subscription = $creationResult['subscription'];
        $this->createdSubscriptionIds[] = $subscription->id;

        $payment = $creationResult['payment'];

        $initialAttempts = $this->attemptRepository
            ->findByPaymentId($payment->id);

        $this->assertCount(1, $initialAttempts);
        $this->assertSame(1, $initialAttempts[0]->attemptNumber);
        $this->assertSame('pending', $initialAttempts[0]->status);


        // Simulate the initial checkout failing before retrying.
        $this->assertTrue(
            $this->attemptRepository->updateStatus(
                $initialAttempts[0]->id,
                'failed',
                'Initial checkout failed.'
            )
        );

        // First retry creates attempt #2 and fails definitively.
        try {
            $service->resumePendingSubscriptionPayment(
                $this->userId
            );

            $this->fail('The first retry should fail.');
        } catch (
        \App\Exceptions\PaystackInitializationException $exception
        ) {
            $this->assertFalse($exception->outcomeUnknown);
        }

        $attemptsAfterFirstRetry = $this->attemptRepository
            ->findByPaymentId($payment->id);

        $this->assertCount(2, $attemptsAfterFirstRetry);
        $this->assertSame(1, $attemptsAfterFirstRetry[0]->attemptNumber);
        $this->assertSame(2, $attemptsAfterFirstRetry[1]->attemptNumber);
        $this->assertSame('failed', $attemptsAfterFirstRetry[0]->status);
        $this->assertSame('failed', $attemptsAfterFirstRetry[1]->status);

        // Second retry must create attempt #3.
        $secondRetryResult = $service->resumePendingSubscriptionPayment(
            $this->userId
        );

        $this->assertSame(
            $secondRetryReference,
            $secondRetryResult['reference']
        );

        $this->assertSame(
            'https://example.test/retry-3',
            $secondRetryResult['authorization_url']
        );

        $this->assertSame(
            'retry-3-access-code',
            $secondRetryResult['access_code']
        );

        $finalAttempts = $this->attemptRepository
            ->findByPaymentId($payment->id);

        $this->assertCount(3, $finalAttempts);

        $this->assertSame(1, $finalAttempts[0]->attemptNumber);
        $this->assertSame(2, $finalAttempts[1]->attemptNumber);
        $this->assertSame(3, $finalAttempts[2]->attemptNumber);

        $this->assertSame('pending', $finalAttempts[2]->status);
        $this->assertSame(
            $secondRetryReference,
            $finalAttempts[2]->reference
        );

        // The parent payment must retain its original reference.
        $updatedPayment = $this->paymentRepository
            ->findById($payment->id);

        $this->assertSame('pending', $updatedPayment->status);
        $this->assertSame(
            $initialReference,
            $updatedPayment->reference
        );

        // Subscription remains pending throughout retry processing.
        $updatedSubscription = $this->subscriptionRepository
            ->findById($subscription->id);

        $this->assertSame('pending', $updatedSubscription->status);
    }

    public function testUnknownInitializationOutcomeDuringRetryBlocksFurtherAttempts(): void
    {
        $this->configureUser();

        $planId = $this->createPlan();

        $initialReference = 'TEST_UNKNOWN_RETRY_INITIAL_' .
            bin2hex(random_bytes(8));

        $retryReference = 'TEST_UNKNOWN_RETRY_2_' .
            bin2hex(random_bytes(8));

        $paystack = $this->createMock(PaystackService::class);

        $paystack
            ->expects($this->exactly(2))
            ->method('generateReference')
            ->willReturnOnConsecutiveCalls(
                $initialReference,
                $retryReference
            );

        $paystack
            ->expects($this->exactly(2))
            ->method('initializeTransaction')
            ->willReturnOnConsecutiveCalls(
                [
                    'reference' => $initialReference,
                    'authorization_url' => 'https://example.test/initial',
                    'access_code' => 'initial-access-code',
                ],
                $this->throwException(
                    new \App\Exceptions\PaystackInitializationException(
                        'Paystack initialization timed out.',
                        true
                    )
                )
            );

        $service = $this->createService($paystack);

        // Create initial subscription and attempt #1.
        $creationResult = $service->createSubscription(
            $this->userId,
            $planId,
            'monthly'
        );

        $subscription = $creationResult['subscription'];
        $this->createdSubscriptionIds[] = $subscription->id;

        $payment = $creationResult['payment'];

        $initialAttempts = $this->attemptRepository
            ->findByPaymentId($payment->id);

        $this->assertCount(1, $initialAttempts);

        $this->assertTrue(
            $this->attemptRepository->updateStatus(
                $initialAttempts[0]->id,
                'failed',
                'Previous checkout failed.'
            )
        );

        // Retry creates attempt #2, but its outcome becomes unknown.
        $exception = null;

        try {
            $service->resumePendingSubscriptionPayment(
                $this->userId
            );
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(
            \App\Exceptions\PaystackInitializationException::class,
            $exception
        );

        $this->assertTrue($exception->outcomeUnknown);

        // Confirm attempt #2 is unresolved.
        $attempts = $this->attemptRepository
            ->findByPaymentId($payment->id);

        $this->assertCount(2, $attempts);

        $this->assertSame(1, $attempts[0]->attemptNumber);
        $this->assertSame(2, $attempts[1]->attemptNumber);

        $this->assertSame('failed', $attempts[0]->status);

        $this->assertSame(
            'initialization_unknown',
            $attempts[1]->status
        );

        $this->assertSame(
            $retryReference,
            $attempts[1]->reference
        );

        // Parent payment and subscription must remain pending.
        $updatedPayment = $this->paymentRepository
            ->findById($payment->id);

        $updatedSubscription = $this->subscriptionRepository
            ->findById($subscription->id);

        $this->assertSame('pending', $updatedPayment->status);
        $this->assertSame('pending', $updatedSubscription->status);

        $this->assertSame(
            $initialReference,
            $updatedPayment->reference
        );

        // A subsequent retry must be blocked before another Paystack call.
        $secondException = null;

        try {
            $service->resumePendingSubscriptionPayment(
                $this->userId
            );
        } catch (\Throwable $e) {
            $secondException = $e;
        }

        $this->assertNotNull($secondException);

        $this->assertStringContainsString(
            'Manual reconciliation is required',
            $secondException->getMessage()
        );

        // No third attempt should have been created.
        $finalAttempts = $this->attemptRepository
            ->findByPaymentId($payment->id);

        $this->assertCount(2, $finalAttempts);
    }


    public function testResumeAcceptsSuccessfulInitializationWithoutAccessCode(): void
    {
        $this->configureUser();

        $planId = $this->createPlan();

        $initialReference = 'TEST_NO_ACCESS_INITIAL_' .
            bin2hex(random_bytes(8));

        $retryReference = 'TEST_NO_ACCESS_RETRY_' .
            bin2hex(random_bytes(8));

        $paystack = $this->createMock(PaystackService::class);

        $paystack
            ->expects($this->exactly(2))
            ->method('generateReference')
            ->willReturnOnConsecutiveCalls(
                $initialReference,
                $retryReference
            );

        $paystack
            ->expects($this->exactly(2))
            ->method('initializeTransaction')
            ->willReturnOnConsecutiveCalls(
                [
                    'reference' => $initialReference,
                    'authorization_url' => 'https://example.test/initial',
                    'access_code' => 'initial-access-code',
                ],
                [
                    'reference' => $retryReference,
                    'authorization_url' => 'https://example.test/retry',
                ]
            );

        $service = $this->createService($paystack);

        $creationResult = $service->createSubscription(
            $this->userId,
            $planId,
            'monthly'
        );

        $subscription = $creationResult['subscription'];
        $this->createdSubscriptionIds[] = $subscription->id;

        $payment = $creationResult['payment'];

        $initialAttempts = $this->attemptRepository
            ->findByPaymentId($payment->id);

        $this->assertCount(1, $initialAttempts);

        $this->assertTrue(
            $this->attemptRepository->updateStatus(
                $initialAttempts[0]->id,
                'failed',
                'Previous checkout failed.'
            )
        );

        $result = $service->resumePendingSubscriptionPayment(
            $this->userId
        );

        $this->assertSame($retryReference, $result['reference']);

        $this->assertSame(
            'https://example.test/retry',
            $result['authorization_url']
        );

        $this->assertNull($result['access_code']);

        $attempts = $this->attemptRepository
            ->findByPaymentId($payment->id);

        $this->assertCount(2, $attempts);

        $this->assertSame('pending', $attempts[1]->status);
        $this->assertSame($retryReference, $attempts[1]->reference);
        $this->assertNull($attempts[1]->accessCode);

        $updatedPayment = $this->paymentRepository
            ->findById($payment->id);

        $updatedSubscription = $this->subscriptionRepository
            ->findById($subscription->id);

        $this->assertSame('pending', $updatedPayment->status);
        $this->assertSame('pending', $updatedSubscription->status);

        $this->assertSame(
            $initialReference,
            $updatedPayment->reference
        );
    }

}
