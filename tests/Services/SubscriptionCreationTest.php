<?php

declare(strict_types=1);


use App\Helpers\DatabaseManager;
use App\Models\Barber;
use App\Models\Shop;
use App\Models\User;
use App\Repositories\BarberRepository;
use App\Repositories\PlanRepository;
use App\Repositories\PlanVersionRepository;
use App\Repositories\ShopRepository;
use App\Repositories\SubscriptionEventRepository;
use App\Repositories\SubscriptionPaymentRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\UserRepository;
use App\Repositories\SubscriptionPaymentAttemptRepository;
use App\Services\PaystackService;
use App\Services\SubscriptionService;
use PHPUnit\Framework\TestCase;

final class SubscriptionCreationTest extends TestCase
{
    private PDO $db;

    private SubscriptionRepository $subscriptionRepository;
    private SubscriptionPaymentRepository $paymentRepository;
    private SubscriptionEventRepository $eventRepository;
    private SubscriptionPaymentAttemptRepository $attemptRepository;

    private UserRepository $userRepository;
    private BarberRepository $barberRepository;
    private ShopRepository $shopRepository;

    private int $userId;
    private int $beneficiaryId;
    private int $shopId;

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

        $this->subscriptionRepository =
            new SubscriptionRepository();

        $this->paymentRepository =
            new SubscriptionPaymentRepository();

        $this->attemptRepository =
            new SubscriptionPaymentAttemptRepository();

        $this->eventRepository =
            new SubscriptionEventRepository();

        $this->userId = random_int(100000000, 999999999);
        $this->beneficiaryId = random_int(100000000, 999999999);
        $this->shopId = random_int(100000000, 999999999);

        $this->userRepository = $this->createMock(
            UserRepository::class
        );

        $this->barberRepository = $this->createMock(
            BarberRepository::class
        );

        $this->shopRepository = $this->createMock(
            ShopRepository::class
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            foreach ($this->createdSubscriptionIds as $id) {
                // Delete subscription events first.
                $stmt = $this->db->prepare("
                    DELETE FROM subscription_events
                    WHERE subscription_id = :id
                ");

                $stmt->execute(['id' => $id]);

                // Delete payment attempts before parent payments.
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
        }

        parent::tearDown();
    }

    private function createPlan(
        string $role,
        string $scope
    ): array {
        $stmt = $this->db->prepare("
            INSERT INTO plans (
                name,
                role,
                scope,
                features
            )
            VALUES (
                :name,
                :role,
                :scope,
                :features
            )
        ");

        $stmt->execute([
            'name' => 'Creation Test ' . bin2hex(random_bytes(4)),
            'role' => $role,
            'scope' => $scope,
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

        $versionId = (int) $this->db->lastInsertId();

        $this->createdPlanVersionIds[] = $versionId;

        return [
            'plan_id' => $planId,
            'version_id' => $versionId,
        ];
    }

    private function configureUser(string $role): void
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
            ->willReturn([$role]);
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

    private function createSuccessfulPaystackMock(
        string $reference
    ): PaystackService {
        $paystack = $this->createMock(
            PaystackService::class
        );

        $paystack
            ->method('generateReference')
            ->willReturn($reference);

        $paystack
            ->method('initializeTransaction')
            ->willReturn([
                'reference' => $reference,
                'authorization_url' => 'https://example.test/pay',
                'access_code' => 'test-access-code',
            ]);

        return $paystack;
    }

    public function testBarberCanCreateHomeSubscription(): void
    {
        $this->configureUser('BARBER');

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

        $plan = $this->createPlan('BARBER', 'HOME');

        $reference = 'TEST_HOME_' . bin2hex(random_bytes(8));

        $service = $this->createService(
            $this->createSuccessfulPaystackMock($reference)
        );

        $result = $service->createSubscription(
            $this->userId,
            $plan['plan_id'],
            'monthly'
        );

        $subscription = $result['subscription'];

        $this->createdSubscriptionIds[] = $subscription->id;

        $this->assertSame('VALID', $subscription->entitlementStatus);
        $this->assertSame('HOME', $subscription->scope);
        $this->assertSame('BARBER', $subscription->beneficiaryType);
        $this->assertSame($this->beneficiaryId, $subscription->beneficiaryId);
        $this->assertSame('pending', $subscription->status);

        $this->assertSame(25.00, $result['amount']);
        $this->assertSame($reference, $result['reference']);

        $payment = $this->paymentRepository
            ->findBySubscriptionId($subscription->id);

        $this->assertCount(1, $payment);
        $this->assertSame('pending', $payment[0]->status);
    }

    public function testBarberCannotCreateDuplicatePendingHomeSubscription(): void
    {
        $this->configureUser('BARBER');

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

        $plan = $this->createPlan('BARBER', 'HOME');

        // First subscription request should succeed.
        $firstReference = 'TEST_FIRST_' . bin2hex(random_bytes(8));

        $firstService = $this->createService(
            $this->createSuccessfulPaystackMock($firstReference)
        );

        $firstResult = $firstService->createSubscription(
            $this->userId,
            $plan['plan_id'],
            'monthly'
        );

        $firstSubscription = $firstResult['subscription'];

        $this->createdSubscriptionIds[] = $firstSubscription->id;

        $this->assertSame('pending', $firstSubscription->status);

        // Second request must be rejected before Paystack is contacted.
        $paystack = $this->createMock(PaystackService::class);

        $paystack
            ->expects($this->never())
            ->method('generateReference');

        $paystack
            ->expects($this->never())
            ->method('initializeTransaction');

        $secondService = $this->createService($paystack);

        $exception = null;

        try {
            $secondService->createSubscription(
                $this->userId,
                $plan['plan_id'],
                'monthly'
            );
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNotNull(
            $exception,
            'A duplicate pending HOME subscription should be rejected.'
        );

        // Confirm only one pending entitlement exists.
        $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM subscriptions
        WHERE beneficiary_type = 'BARBER'
          AND beneficiary_id = :beneficiary_id
          AND scope = 'HOME'
          AND entitlement_status = 'VALID'
          AND status = 'pending'
    ");

        $stmt->execute([
            'beneficiary_id' => $this->beneficiaryId,
        ]);

        $this->assertSame(1, (int) $stmt->fetchColumn());

        // Confirm the original subscription remains pending.
        $originalSubscription = $this->subscriptionRepository
            ->findById($firstSubscription->id);

        $this->assertSame('pending', $originalSubscription->status);

        // Confirm only the original payment was created.
        $payments = $this->paymentRepository
            ->findBySubscriptionId($firstSubscription->id);

        $this->assertCount(1, $payments);
        $this->assertSame('pending', $payments[0]->status);
    }


    public function testBarberCannotCreateDuplicateActiveHomeSubscription(): void
    {
        $this->configureUser('BARBER');

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

        $plan = $this->createPlan('BARBER', 'HOME');

        // Create the first subscription.
        $firstReference = 'TEST_ACTIVE_' . bin2hex(random_bytes(8));

        $firstService = $this->createService(
            $this->createSuccessfulPaystackMock($firstReference)
        );

        $firstResult = $firstService->createSubscription(
            $this->userId,
            $plan['plan_id'],
            'monthly'
        );

        $firstSubscription = $firstResult['subscription'];

        $this->createdSubscriptionIds[] = $firstSubscription->id;

        // Activate the first subscription with a future expiry date.
        $startDate = date('Y-m-d H:i:s', strtotime('-1 day'));
        $endDate = date('Y-m-d H:i:s', strtotime('+30 days'));

        $activated = $this->subscriptionRepository->activate(
            $firstSubscription->id,
            $startDate,
            $endDate
        );

        $this->assertTrue($activated);

        // The second request must be rejected before Paystack is contacted.
        $paystack = $this->createMock(PaystackService::class);

        $paystack
            ->expects($this->never())
            ->method('generateReference');

        $paystack
            ->expects($this->never())
            ->method('initializeTransaction');

        $secondService = $this->createService($paystack);

        $exception = null;

        try {
            $secondService->createSubscription(
                $this->userId,
                $plan['plan_id'],
                'monthly'
            );
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNotNull(
            $exception,
            'A duplicate active HOME subscription should be rejected.'
        );

        $this->assertSame(
            'An active subscription already exists for this entitlement.',
            $exception->getMessage()
        );

        // Verify only one active entitlement exists.
        $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM subscriptions
        WHERE beneficiary_type = 'BARBER'
          AND beneficiary_id = :beneficiary_id
          AND scope = 'HOME'
          AND entitlement_status = 'VALID'
          AND status = 'active'
          AND start_date <= CURRENT_TIMESTAMP
          AND end_date > CURRENT_TIMESTAMP
    ");

        $stmt->execute([
            'beneficiary_id' => $this->beneficiaryId,
        ]);

        $this->assertSame(1, (int) $stmt->fetchColumn());

        // Confirm the original subscription remains active.
        $originalSubscription = $this->subscriptionRepository
            ->findById($firstSubscription->id);

        $this->assertSame('active', $originalSubscription->status);
        $this->assertSame('VALID', $originalSubscription->entitlementStatus);
    }


    public function testShopOwnerCanCreateShopSubscription(): void
    {
        $this->configureUser('SHOP_OWNER');

        $this->shopRepository
            ->method('findById')
            ->with($this->shopId)
            ->willReturn(new Shop([
                'id' => $this->shopId,
                'owner_id' => $this->userId,
                'name' => 'Test Shop',
                'location' => 'Accra',
                'approval_status' => 'approved',
                'status' => 'active',
            ]));

        $plan = $this->createPlan('SHOP_OWNER', 'SHOP');

        $reference = 'TEST_SHOP_' . bin2hex(random_bytes(8));

        $service = $this->createService(
            $this->createSuccessfulPaystackMock($reference)
        );

        $result = $service->createSubscription(
            $this->userId,
            $plan['plan_id'],
            'monthly',
            $this->shopId
        );

        $subscription = $result['subscription'];

        $this->createdSubscriptionIds[] = $subscription->id;

        $this->assertSame('VALID', $subscription->entitlementStatus);
        $this->assertSame('SHOP', $subscription->scope);
        $this->assertSame('SHOP', $subscription->beneficiaryType);
        $this->assertSame($this->shopId, $subscription->beneficiaryId);
        $this->assertSame('pending', $subscription->status);
    }

    public function testVendorCanCreateVendorSubscription(): void
    {
        $this->configureUser('VENDOR');

        $plan = $this->createPlan('VENDOR', 'VENDOR');

        $reference = 'TEST_VENDOR_' . bin2hex(random_bytes(8));

        $service = $this->createService(
            $this->createSuccessfulPaystackMock($reference)
        );

        $result = $service->createSubscription(
            $this->userId,
            $plan['plan_id'],
            'monthly'
        );

        $subscription = $result['subscription'];

        $this->createdSubscriptionIds[] = $subscription->id;

        $this->assertSame('VALID', $subscription->entitlementStatus);
        $this->assertSame('VENDOR', $subscription->scope);
        $this->assertSame('VENDOR', $subscription->beneficiaryType);
        $this->assertSame($this->userId, $subscription->beneficiaryId);
        $this->assertSame('pending', $subscription->status);
    }

    public function testShopOwnerCannotSubscribeToAnotherOwnersShop(): void
    {
        $this->configureUser('SHOP_OWNER');

        $this->shopRepository
            ->method('findById')
            ->with($this->shopId)
            ->willReturn(new Shop([
                'id' => $this->shopId,
                'owner_id' => $this->userId + 1,
                'name' => 'Another Owner Shop',
                'location' => 'Accra',
                'approval_status' => 'approved',
                'status' => 'active',
            ]));

        $plan = $this->createPlan('SHOP_OWNER', 'SHOP');

        $service = $this->createService(
            $this->createSuccessfulPaystackMock('UNUSED_REFERENCE')
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'The selected shop does not belong to your account.'
        );

        $service->createSubscription(
            $this->userId,
            $plan['plan_id'],
            'monthly',
            $this->shopId
        );
    }

    public function testUnknownPaystackInitializationOutcomePreservesPendingSubscription(): void
    {
        $this->configureUser('BARBER');

        $this->barberRepository
            ->method('findByUserId')
            ->willReturn(new Barber([
                'id' => $this->beneficiaryId,
                'user_id' => $this->userId,
                'approval_status' => 'approved',
                'status' => 'active',
            ]));

        $plan = $this->createPlan('BARBER', 'HOME');

        $reference = 'TEST_UNKNOWN_' . bin2hex(random_bytes(8));

        $paystack = $this->createMock(PaystackService::class);

        $paystack
            ->method('generateReference')
            ->willReturn($reference);

        $paystack
            ->method('initializeTransaction')
            ->willThrowException(
                new \App\Exceptions\PaystackInitializationException(
                    'Simulated unknown Paystack outcome.',
                    true
                )
            );

        $service = $this->createService($paystack);

        try {
            $service->createSubscription(
                $this->userId,
                $plan['plan_id'],
                'monthly'
            );

            $this->fail('Expected Paystack initialization to throw an exception.');

        } catch (\App\Exceptions\PaystackInitializationException $exception) {
            $this->assertSame(
                'Simulated unknown Paystack outcome.',
                $exception->getMessage()
            );

            $this->assertTrue($exception->outcomeUnknown);
        }

        // Retrieve the subscription created before Paystack initialization.
        $stmt = $this->db->prepare("
        SELECT id
        FROM subscriptions
        WHERE beneficiary_type = 'BARBER'
          AND beneficiary_id = :beneficiary_id
          AND scope = 'HOME'
        ORDER BY id DESC
        LIMIT 1
    ");

        $stmt->execute([
            'beneficiary_id' => $this->beneficiaryId,
        ]);

        $subscriptionId = (int) $stmt->fetchColumn();

        $this->assertGreaterThan(0, $subscriptionId);

        $this->createdSubscriptionIds[] = $subscriptionId;

        $subscription = $this->subscriptionRepository
            ->findById($subscriptionId);

        $this->assertSame('pending', $subscription->status);

        // The parent payment must remain pending.
        $payments = $this->paymentRepository
            ->findBySubscriptionId($subscriptionId);

        $this->assertCount(1, $payments);
        $this->assertSame('pending', $payments[0]->status);

        // The Paystack attempt must retain its unknown outcome.
        $stmt = $this->db->prepare("
        SELECT payment_id, attempt_number, reference, status
        FROM subscription_payment_attempts
        WHERE payment_id = :payment_id
    ");

        $stmt->execute([
            'payment_id' => $payments[0]->id,
        ]);

        $attempts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(1, $attempts);
        $this->assertSame('initialization_unknown', $attempts[0]['status']);
        $this->assertSame(1, (int) $attempts[0]['attempt_number']);
        $this->assertSame($reference, $attempts[0]['reference']);

        // An unknown outcome must not generate a cancellation event.
        $events = $this->eventRepository
            ->findBySubscriptionId($subscriptionId);

        $cancellationEvents = array_filter(
            $events,
            fn (array $event): bool =>
                $event['event_type'] === 'SUBSCRIPTION_CANCELLED'
        );

        $this->assertCount(0, $cancellationEvents);
    }

    public function testPaystackInitializationFailureCancelsSubscription(): void
    {
        $this->configureUser('BARBER');

        $this->barberRepository
            ->method('findByUserId')
            ->willReturn(new Barber([
                'id' => $this->beneficiaryId,
                'user_id' => $this->userId,
                'approval_status' => 'approved',
                'status' => 'active',
            ]));

        $plan = $this->createPlan('BARBER', 'HOME');

        $reference = 'TEST_FAILURE_' . bin2hex(random_bytes(8));

        $paystack = $this->createMock(PaystackService::class);

        $paystack
            ->method('generateReference')
            ->willReturn($reference);

        $paystack
            ->method('initializeTransaction')
            ->willThrowException(
                new \App\Exceptions\PaystackInitializationException(
                    'Simulated Paystack rejection.',
                    false
                )
            );

        $service = $this->createService($paystack);

        try {
            $service->createSubscription(
                $this->userId,
                $plan['plan_id'],
                'monthly'
            );

            $this->fail('Expected Paystack initialization to fail.');

        } catch (\Exception $exception) {
            $this->assertSame(
                'Simulated Paystack rejection.',
                $exception->getMessage()
            );
        }

        $stmt = $this->db->prepare("
            SELECT id
            FROM subscriptions
            WHERE beneficiary_type = 'BARBER'
              AND beneficiary_id = :beneficiary_id
              AND scope = 'HOME'
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            'beneficiary_id' => $this->beneficiaryId,
        ]);

        $subscriptionId = (int) $stmt->fetchColumn();

        $this->createdSubscriptionIds[] = $subscriptionId;

        $subscription = $this->subscriptionRepository
            ->findById($subscriptionId);

        $this->assertSame('cancelled', $subscription->status);
        $this->assertSame('VALID', $subscription->entitlementStatus);

        $payments = $this->paymentRepository
            ->findBySubscriptionId($subscriptionId);

        $this->assertCount(1, $payments);
        $this->assertSame('failed', $payments[0]->status);
    }
}