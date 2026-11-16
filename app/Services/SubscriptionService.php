<?php

namespace App\Services;
use App\Exceptions\PaystackInitializationException;

use App\Repositories\PlanRepository;
use App\Repositories\PlanVersionRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\SubscriptionPaymentRepository;
use App\Repositories\SubscriptionPaymentAttemptRepository;
use App\Repositories\UserRepository;
use App\Repositories\BarberRepository;
use App\Repositories\ShopRepository;
use App\Repositories\SubscriptionEventRepository;
use Exception;

class SubscriptionService
{
    private PlanRepository $planRepository;
    private PlanVersionRepository $planVersionRepository;
    private SubscriptionRepository $subscriptionRepository;
    private SubscriptionPaymentRepository $paymentRepository;
    private SubscriptionPaymentAttemptRepository $attemptRepository;
    private UserRepository $userRepository;
    private BarberRepository $barberRepository;
    private ShopRepository $shopRepository;
    private SubscriptionEventRepository $eventRepository;
    private PaystackService $paystackService;
    public function __construct(
        PlanRepository $planRepository,
        PlanVersionRepository $planVersionRepository,
        SubscriptionRepository $subscriptionRepository,
        SubscriptionPaymentRepository $paymentRepository,
        UserRepository $userRepository,
        BarberRepository $barberRepository,
        ShopRepository $shopRepository,
        SubscriptionEventRepository $eventRepository,
        SubscriptionPaymentAttemptRepository $attemptRepository,
        PaystackService $paystackService
    ) {
        $this->planRepository = $planRepository;
        $this->planVersionRepository = $planVersionRepository;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->paymentRepository = $paymentRepository;
        $this->userRepository = $userRepository;
        $this->barberRepository = $barberRepository;
        $this->shopRepository = $shopRepository;
        $this->eventRepository = $eventRepository;
        $this->attemptRepository = $attemptRepository;
        $this->paystackService = $paystackService;
    }

    /**
     * Make sure the authenticated user is a FINANCE_MANAGER.
     */
    public function validateFinanceManager(
        int $userId
    ): void {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new Exception(
                'Finance Manager user not found.'
            );
        }

        $roles = $this->userRepository->getRoles($userId);

        if (!in_array('FINANCE_MANAGER', $roles, true)) {
            throw new Exception(
                'Access denied. Finance Manager privileges required.'
            );
        }
    }

    /**
     * Make sure the authenticated user is an ADMIN.
     */
    public function validateAdmin(
        int $userId
    ): void {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new Exception(
                'Admin user not found.'
            );
        }

        $roles = $this->userRepository->getRoles($userId);

        if (!in_array('ADMIN', $roles, true)) {
            throw new Exception(
                'Access denied. Admin privileges required.'
            );
        }
    }


    public function validateSubscriberRole(
        int $userId,
        string $requiredRole
    ): void {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new Exception('Subscriber user not found.');
        }

        $roles = $this->userRepository->getRoles($userId);

        if (!in_array($requiredRole, $roles, true)) {
            throw new Exception(
                'User does not have the required role: ' . $requiredRole
            );
        }
    }


    public function getApprovedPricing(int $planId): \App\Models\PlanVersion
    {
        $plan = $this->planRepository->findById($planId);

        if (!$plan) {
            throw new Exception('Subscription plan not found.');
        }

        $version = $this->planVersionRepository->findApprovedByPlanId(
            $planId
        );

        if ($version === null) {
            throw new Exception(
                'No approved pricing is currently available for this plan.'
            );
        }

        return $version;
    }

    public function calculateSubscriptionAmount(
        int $planVersionId,
        string $billingPeriod
    ): float {
        $version = $this->planVersionRepository->findById(
            $planVersionId
        );

        if ($version === null) {
            throw new Exception('Pricing version not found.');
        }

        if ($version->status !== 'approved') {
            throw new Exception(
                'Only approved pricing can be used for subscriptions.'
            );
        }

        if (!in_array($billingPeriod, ['monthly', 'yearly'], true)) {
            throw new Exception(
                'Invalid billing period. Use monthly or yearly.'
            );
        }

        if ($billingPeriod === 'monthly') {
            return round($version->monthlyPrice, 2);
        }

        return $this->calculateYearlyPrice($planVersionId);
    }


    public function getCurrentSubscription(int $userId): array
    {
        $activeSubscription =
            $this->subscriptionRepository->findActiveByUserId($userId);

        if ($activeSubscription) {
            return [
                'status' => 'active',
                'subscription' => $activeSubscription,
                'payment' => null
            ];
        }

        $pendingSubscription =
            $this->subscriptionRepository->findPendingByUserId($userId);

        if ($pendingSubscription) {

            $payment =
                $this->paymentRepository
                    ->findBySubscriptionId($pendingSubscription->id);

            return [
                'status' => 'pending',
                'subscription' => $pendingSubscription,
                'payment' => $payment
            ];
        }

        return [
            'status' => 'none',
            'subscription' => null,
            'payment' => null
        ];
    }

    /**
     * Finance Manager proposes new subscription pricing.
     */
    public function proposePricing(
        int $financeManagerId,
        int $planId,
        float $monthlyPrice,
        float $yearlyDiscountPercent
    ): int {
        $this->validateFinanceManager(
            $financeManagerId
        );

        if ($monthlyPrice <= 0) {
            throw new Exception(
                'Monthly subscription price must be greater than zero.'
            );
        }

        if (
            $yearlyDiscountPercent < 0 ||
            $yearlyDiscountPercent > 100
        ) {
            throw new Exception(
                'Yearly discount must be between 0 and 100 percent.'
            );
        }

        $plan = $this->planRepository->findById(
            $planId
        );

        if (!$plan) {
            throw new Exception(
                'Subscription plan not found.'
            );
        }

        $existingPending =
            $this->planVersionRepository
                ->findPendingByPlanId($planId);

        if ($existingPending !== null) {
            throw new Exception(
                'This plan already has a pending pricing proposal.'
            );
        }

        $version = $this->planVersionRepository->create(
            $planId,
            $monthlyPrice,
            $yearlyDiscountPercent,
            $financeManagerId
        );

        if ($version === null) {
            throw new Exception('Unable to create pricing proposal.');
        }

        $eventCreated = $this->eventRepository->create(
            null,
            $version->id,
            null,
            'PLAN_PRICING_PROPOSED',
            'Subscription pricing proposal created for plan ID ' . $planId . '.',
            $financeManagerId
        );

        if (!$eventCreated) {
            throw new Exception(
                'Pricing proposal was created, but the audit event could not be recorded.'
            );
        }

        return $version->id;
    }

    /**
     * Admin gets pending pricing proposals.
     */
    public function getPendingPricing(
        int $adminId
    ): array {
        $this->validateAdmin($adminId);

        return $this->planVersionRepository
            ->findPending();
    }

    /**
     * Admin approves or rejects pricing.
     */
    public function updatePricingApproval(
        int $adminId,
        int $planVersionId,
        string $approvalStatus,
        ?string $rejectionReason = null
    ): bool {
        $this->validateAdmin($adminId);

        if (
            !in_array(
                $approvalStatus,
                ['approved', 'rejected'],
                true
            )
        ) {
            throw new Exception(
                'Invalid approval status. Use approved or rejected.'
            );
        }

        $version =
            $this->planVersionRepository
                ->findById($planVersionId);

        if (!$version) {
            throw new Exception(
                'Pricing version not found.'
            );
        }

        if ($version->status !== 'pending') {
            throw new Exception(
                'Only pending pricing proposals can be approved or rejected.'
            );
        }

        if (
            $approvalStatus === 'rejected' &&
            (
                $rejectionReason === null ||
                trim($rejectionReason) === ''
            )
        ) {
            throw new Exception(
                'A rejection reason is required.'
            );
        }

        $result = $this->planVersionRepository->updateApprovalStatus(
            $planVersionId,
            $approvalStatus,
            $adminId,
            $rejectionReason
        );

        if (!$result) {
            return false;
        }

        $eventType = $approvalStatus === 'approved'
            ? 'PLAN_PRICING_APPROVED'
            : 'PLAN_PRICING_REJECTED';

        $description = $approvalStatus === 'approved'
            ? 'Subscription pricing proposal approved.'
            : 'Subscription pricing proposal rejected. Reason: ' .
            ($rejectionReason ?? 'No reason provided.');

        $eventCreated = $this->eventRepository->create(
            null,
            $planVersionId,
            null,
            $eventType,
            $description,
            $adminId
        );

        if (!$eventCreated) {
            throw new Exception(
                'Pricing approval was updated, but the audit event could not be recorded.'
            );
        }

        return true;
    }

    /**
     * Create a pending subscription and initialize
     * the corresponding Paystack payment.
     *
     * The subscription remains pending until the
     * payment has been successfully verified.
     */
    public function createSubscription(
        int $userId,
        int $planId,
        string $billingPeriod,
        ?int $shopId = null
    ): array {
        // Get the selected plan.
        $plan = $this->planRepository->findById($planId);

        if (!$plan) {
            throw new Exception('Subscription plan not found.');
        }

        // Validate the authenticated user's role.
        $this->validateSubscriberRole(
            $userId,
            $plan->role
        );

        // Resolve the beneficiary using server-side records.
        $beneficiary = $this->resolveSubscriptionBeneficiary(
            $userId,
            $plan,
            $shopId
        );

        $beneficiaryType = $beneficiary['beneficiary_type'];
        $beneficiaryId = $beneficiary['beneficiary_id'];
        $scope = $beneficiary['scope'];

        // Prevent duplicate pending entitlements for this beneficiary.
        $existingPending = $this->subscriptionRepository
            ->findPendingByBeneficiary(
                $beneficiaryType,
                $beneficiaryId,
                $scope
            );

        if ($existingPending !== null) {
            throw new Exception(
                'A pending subscription already exists for this entitlement.'
            );
        }

        // Prevent creating another active entitlement.
        $existingActive = $this->subscriptionRepository
            ->findActiveByBeneficiary(
                $beneficiaryType,
                $beneficiaryId,
                $scope
            );

        if ($existingActive !== null) {
            throw new Exception(
                'An active subscription already exists for this entitlement.'
            );
        }

        // Get the currently approved pricing version.
        $pricing = $this->getApprovedPricing($planId);

        // Calculate the authoritative subscription amount.
        $amount = $this->calculateSubscriptionAmount(
            $pricing->id,
            $billingPeriod
        );

        // Get the authenticated payer's email.
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new Exception('User not found.');
        }

        if (empty($user->email)) {
            throw new Exception(
                'User email is required for subscription payment.'
            );
        }

        // Create the pending VALID entitlement first.
        $subscription = $this->subscriptionRepository
            ->createEntitlement(
                $userId,
                $planId,
                $pricing->id,
                $billingPeriod,
                $scope,
                $beneficiaryType,
                $beneficiaryId
            );

        if ($subscription === null) {
            throw new Exception(
                'Unable to create subscription entitlement.'
            );
        }


        // Create the local pending payment before contacting Paystack.
        try {
            $paystackInitializationStarted = false;
            $paystackInitializationSucceeded = false;

            // Generate the payment reference on the backend.
            $reference = $this->paystackService->generateReference();

            $payment = $this->paymentRepository->create(
                $subscription->id,
                $amount,
                'GHS',
                $reference
            );

            if ($payment === null) {
                throw new Exception(
                    'Unable to create subscription payment record.'
                );
            }

            // Create the first Paystack attempt before initialization.
            $attempt = $this->attemptRepository->create(
                $payment->id,
                1,
                $reference
            );

            if ($attempt === null) {
                throw new Exception(
                    'Unable to create subscription payment attempt.'
                );
            }


            // Record subscription creation.
            $eventCreated = $this->eventRepository->create(
                $subscription->id,
                $pricing->id,
                $userId,
                'SUBSCRIPTION_CREATED',
                'Pending ' . $billingPeriod .
                ' subscription created for plan ID ' . $planId .
                ', scope ' . $scope .
                ', beneficiary ' . $beneficiaryType .
                ' #' . $beneficiaryId . '.',
                $userId
            );

            if (!$eventCreated) {
                throw new Exception(
                    'Unable to record subscription creation event.'
                );
            }

            $paystackInitializationStarted = true;

            // Initialize the Paystack transaction.
            $paystackTransaction = $this->paystackService
                ->initializeTransaction(
                    $user->email,
                    $amount,
                    $reference,
                    [
                        'type' => 'subscription',
                        'user_id' => $userId,
                        'subscription_id' => $subscription->id,
                        'plan_id' => $planId,
                        'plan_version_id' => $pricing->id,
                        'billing_period' => $billingPeriod,
                        'scope' => $scope,
                        'beneficiary_type' => $beneficiaryType,
                        'beneficiary_id' => $beneficiaryId,
                    ]
                );

            $paystackInitializationSucceeded = true;

            // Paystack must return the reference we generated.
            if (
                empty($paystackTransaction['reference']) ||
                $paystackTransaction['reference'] !== $reference
            ) {
                throw new Exception(
                    'Paystack returned an invalid transaction reference.'
                );
            }

            if (empty($paystackTransaction['authorization_url'])) {
                throw new Exception(
                    'Paystack did not return a payment authorization URL.'
                );
            }

            $authorizationUrl =
                $paystackTransaction['authorization_url'];

            $accessCode =
                $paystackTransaction['access_code'] ?? null;

            $checkoutSaved = $this->attemptRepository
                ->updateCheckoutDetails(
                    $attempt->id,
                    $authorizationUrl,
                    $accessCode
                );

            if (!$checkoutSaved) {
                throw new Exception(
                    'Unable to persist Paystack checkout details.'
                );
            }

            return [
                'subscription' => $subscription,
                'payment' => $payment,
                'amount' => $amount,
                'currency' => 'GHS',
                'billing_period' => $billingPeriod,
                'plan_version_id' => $pricing->id,
                'reference' => $reference,
                'authorization_url' => $authorizationUrl,
                'access_code' => $accessCode
            ];

        } catch (\Throwable $exception) {

            /*
             * Determine whether Paystack may have created
             * the transaction.
             */
            $initializationOutcomeUnknown =
                $paystackInitializationSucceeded ||
                (
                    $paystackInitializationStarted &&
                    (
                        !($exception instanceof PaystackInitializationException) ||
                        $exception->outcomeUnknown
                    )
                );

            if ($initializationOutcomeUnknown) {

                /*
                 * Paystack may have created the transaction.
                 *
                 * Preserve the pending payment and subscription.
                 * Do not automatically create another attempt.
                 */
                if (isset($attempt) && $attempt !== null) {
                    try {
                        $attemptUpdated = $this->attemptRepository
                            ->updateStatus(
                                $attempt->id,
                                'initialization_unknown',
                                $exception->getMessage()
                            );

                        if (!$attemptUpdated) {
                            error_log(
                                'Unable to mark payment attempt #' .
                                $attempt->id .
                                ' as initialization_unknown.'
                            );
                        }
                    } catch (\Throwable $e) {
                        error_log(
                            'Unknown initialization state update failed for attempt #' .
                            $attempt->id . ': ' . $e->getMessage()
                        );
                    }
                }

                error_log(
                    'Paystack initialization outcome requires investigation. ' .
                    'Payment reference: ' .
                    ($reference ?? 'unavailable') .
                    '. Reason: ' . $exception->getMessage()
                );

            } else {

                /*
                 * Definitive failure or failure before the
                 * Paystack request was sent.
                 */

                // 1. Mark the attempt as failed.
                if (isset($attempt) && $attempt !== null) {
                    try {
                        $attemptUpdated = $this->attemptRepository
                            ->updateStatus(
                                $attempt->id,
                                'failed',
                                $exception->getMessage()
                            );

                        if (!$attemptUpdated) {
                            error_log(
                                'Unable to mark payment attempt #' .
                                $attempt->id . ' as failed.'
                            );
                        }
                    } catch (\Throwable $e) {
                        error_log(
                            'Attempt failure update failed for attempt #' .
                            $attempt->id . ': ' . $e->getMessage()
                        );
                    }
                }

                // 2. Mark the parent payment as failed.
                if (isset($payment) && $payment !== null) {
                    try {
                        $this->paymentRepository->updateStatus(
                            $payment->id,
                            'failed'
                        );
                    } catch (\Throwable $e) {
                        error_log(
                            'Payment failure update failed for payment #' .
                            $payment->id . ': ' . $e->getMessage()
                        );
                    }
                }

                // 3. Cancel the subscription.
                try {
                    $this->subscriptionRepository->updateStatus(
                        $subscription->id,
                        'cancelled'
                    );
                } catch (\Throwable $e) {
                    error_log(
                        'Subscription cancellation failed for subscription #' .
                        $subscription->id . ': ' . $e->getMessage()
                    );
                }

                // 4. Record the cancellation event.
                try {
                    $eventCreated = $this->eventRepository->create(
                        $subscription->id,
                        $pricing->id,
                        $userId,
                        'SUBSCRIPTION_CANCELLED',
                        'Subscription cancelled because payment initialization failed: ' .
                        $exception->getMessage(),
                        $userId
                    );

                    if (!$eventCreated) {
                        error_log(
                            'Unable to record cancellation event for subscription #' .
                            $subscription->id
                        );
                    }
                } catch (\Throwable $e) {
                    error_log(
                        'Cancellation event creation failed for subscription #' .
                        $subscription->id . ': ' . $e->getMessage()
                    );
                }
            }

            // Preserve the original exception.
            throw $exception;
        }
    }


    /**
     * Verify a subscription payment with Paystack.
     *
     * Each Paystack reference belongs to a payment attempt.
     * Payment completion, attempt verification, subscription
     * activation and audit logging are handled transactionally.
     */
    public function verifySubscriptionPayment(
        string $reference,
        int $userId
    ): array {

        /*
         * STEP 1: Find the payment attempt.
         */
        $attempt = $this->attemptRepository
            ->findByReference($reference);

        if ($attempt === null) {
            throw new Exception(
                'Subscription payment attempt not found.'
            );
        }

        /*
         * STEP 2: Resolve the parent payment.
         */
        $payment = $this->paymentRepository
            ->findById($attempt->paymentId);

        if ($payment === null) {
            throw new Exception(
                'Subscription payment not found.'
            );
        }

        /*
         * STEP 3: Resolve the subscription and verify ownership.
         */
        $subscription = $this->subscriptionRepository
            ->findById($payment->subscriptionId);

        if ($subscription === null) {
            throw new Exception(
                'Subscription associated with this payment was not found.'
            );
        }

        if ($subscription->userId !== $userId) {
            throw new Exception(
                'You are not authorized to verify this payment.'
            );
        }

        /*
         * STEP 4: Verify directly with Paystack.
         *
         * Network requests must happen outside the database
         * transaction to avoid holding database locks.
         */
        $transaction = $this->paystackService
            ->verifyTransaction($reference);

        if (
            ($transaction['reference'] ?? '') !== $reference
        ) {
            throw new Exception(
                'Paystack transaction reference does not match the payment.'
            );
        }

        if (
            ($transaction['status'] ?? '') !== 'success'
        ) {
            throw new Exception(
                'Payment was not successful.'
            );
        }

        /*
         * STEP 5: Validate the initial payment details.
         */
        if (
            strtoupper($transaction['currency'] ?? '')
            !== strtoupper($payment->currency)
        ) {
            throw new Exception(
                'Payment currency does not match the subscription payment.'
            );
        }

        $paidAmount = (int) (
            $transaction['amount'] ?? 0
        );

        $expectedAmount = (int) round(
            $payment->amount * 100
        );

        if ($paidAmount !== $expectedAmount) {
            throw new Exception(
                'Payment amount does not match the subscription amount.'
            );
        }

        /*
         * STEP 6: Begin the database transaction.
         */
        $db = \App\Helpers\DatabaseManager::getConnection(
            'subscription_db'
        );

        $resultStatus = 'success';

        try {

            $db->beginTransaction();

            /*
             * LOCK ORDER:
             *
             * 1. Parent payment
             * 2. Payment attempt
             * 3. Subscription
             *
             * Every verification process must follow this order.
             */

            $lockedPayment = $this->paymentRepository
                ->findByIdForUpdate($payment->id);

            if ($lockedPayment === null) {
                throw new Exception(
                    'Subscription payment could not be found.'
                );
            }

            $lockedAttempt = $this->attemptRepository
                ->findByReferenceForUpdate($reference);

            if ($lockedAttempt === null) {
                throw new Exception(
                    'Subscription payment attempt could not be found.'
                );
            }

            if (
                $lockedAttempt->paymentId !== $lockedPayment->id
            ) {
                throw new Exception(
                    'Payment attempt does not belong to the expected payment.'
                );
            }

            $lockedSubscription = $this->subscriptionRepository
                ->findByIdForUpdate(
                    $lockedPayment->subscriptionId
                );

            if ($lockedSubscription === null) {
                throw new Exception(
                    'Subscription associated with this payment was not found.'
                );
            }

            /*
             * STEP 7: Revalidate ownership and payment details
             * after acquiring database locks.
             */
            if ($lockedSubscription->userId !== $userId) {
                throw new Exception(
                    'You are not authorized to verify this payment.'
                );
            }

            if (
                strtoupper($transaction['currency'] ?? '')
                !== strtoupper($lockedPayment->currency)
            ) {
                throw new Exception(
                    'Payment currency does not match the subscription payment.'
                );
            }

            $lockedExpectedAmount = (int) round(
                $lockedPayment->amount * 100
            );

            if ($paidAmount !== $lockedExpectedAmount) {
                throw new Exception(
                    'Payment amount does not match the subscription amount.'
                );
            }

            /*
             * STEP 8: Define the attempt-success transition.
             *
             * An already-succeeded attempt is idempotent.
             */
            $markAttemptSucceeded = function () use (
                $lockedAttempt
            ): void {

                if ($lockedAttempt->status === 'succeeded') {
                    return;
                }

                $updated = $this->attemptRepository
                    ->updateStatus(
                        $lockedAttempt->id,
                        'succeeded'
                    );

                if (!$updated) {
                    throw new Exception(
                        'Unable to mark subscription payment attempt as successful.'
                    );
                }
            };

            /*
             * STEP 9: Define reconciliation handling.
             *
             * Repeated callbacks must not create duplicate events.
             *
             * pending_review can be escalated to refund_required.
             * refund_required, refunded and resolved must not
             * be downgraded by another callback.
             */
            $recordReconciliation = function (
                string $targetStatus,
                string $description
            ) use (
                $lockedAttempt,
                $lockedSubscription,
                $userId
            ): void {

                $currentStatus =
                    $lockedAttempt->reconciliationStatus;

                if (
                    in_array(
                        $currentStatus,
                        ['refunded', 'resolved'],
                        true
                    )
                ) {
                    return;
                }

                if ($currentStatus === $targetStatus) {
                    return;
                }

                if (
                    $currentStatus === 'refund_required'
                    && $targetStatus !== 'refund_required'
                ) {
                    return;
                }

                if (
                    $currentStatus === 'pending_review'
                    && $targetStatus === 'pending_review'
                ) {
                    return;
                }

                $updated = $this->attemptRepository
                    ->updateReconciliationStatus(
                        $lockedAttempt->id,
                        $targetStatus
                    );

                if (!$updated) {
                    throw new Exception(
                        'Unable to update payment reconciliation status.'
                    );
                }

                $eventCreated = $this->eventRepository->create(
                    $lockedSubscription->id,
                    $lockedSubscription->planVersionId,
                    $userId,
                    'SUBSCRIPTION_PAYMENT_RECONCILIATION_REQUIRED',
                    $description,
                    $userId
                );

                if (!$eventCreated) {
                    throw new Exception(
                        'Unable to record payment reconciliation event.'
                    );
                }
            };

            /*
             * STEP 10: Handle an already-completed parent payment.
             */
            if ($lockedPayment->status === 'completed') {

                /*
                 * Payment completed and subscription active.
                 */
                if ($lockedSubscription->status === 'active') {

                    /*
                     * The activation marker identifies the exact attempt
                     * that originally activated this subscription.
                     */
                    if (
                        $lockedPayment->activatedAttemptId === $lockedAttempt->id
                    ) {

                        /*
                         * Same attempt, already successfully processed.
                         */
                        if (
                            $lockedAttempt->status === 'succeeded'
                            && $lockedAttempt->reconciliationStatus === 'not_required'
                        ) {
                            $resultStatus = 'success';

                        } else {

                            /*
                             * The activation marker and attempt state disagree.
                             * Do not assume this is a duplicate charge.
                             */
                            $recordReconciliation(
                                'pending_review',
                                sprintf(
                                    'Payment attempt #%d (%s) is recorded as the activating attempt, but its status or reconciliation state is inconsistent. Manual review is required.',
                                    $lockedAttempt->attemptNumber,
                                    $reference
                                )
                            );

                            $resultStatus = 'reconciliation_required';
                        }

                    } elseif ($lockedPayment->activatedAttemptId !== null) {

                        /*
                         * Another attempt is recorded as the activating attempt.
                         * This successful attempt requires refund review.
                         */
                        $markAttemptSucceeded();

                        $recordReconciliation(
                            'refund_required',
                            sprintf(
                                'Successful payment attempt #%d (%s) requires refund review because a different attempt already activated the subscription.',
                                $lockedAttempt->attemptNumber,
                                $reference
                            )
                        );

                        $resultStatus = 'reconciliation_required';

                    } else {

                        /*
                         * No activation marker exists.
                         * We cannot reliably establish which attempt activated
                         * the subscription, so require manual investigation.
                         */
                        $markAttemptSucceeded();

                        $recordReconciliation(
                            'pending_review',
                            sprintf(
                                'Successful payment attempt #%d (%s) requires manual reconciliation because the parent payment is completed and the subscription is active, but no activating attempt is recorded.',
                                $lockedAttempt->attemptNumber,
                                $reference
                            )
                        );

                        $resultStatus = 'reconciliation_required';
                    }                } else {

                    /*
                     * A completed payment with a pending subscription
                     * is inconsistent. Do not activate automatically.
                     */
                    $markAttemptSucceeded();
                    $recordReconciliation(
                        'pending_review',
                        sprintf(
                            'Payment attempt #%d (%s) requires manual reconciliation because the parent payment is completed while the subscription remains pending.',
                            $lockedAttempt->attemptNumber,
                            $reference
                        )
                    );

                    $resultStatus = 'reconciliation_required';
                }


            } elseif ($lockedPayment->status === 'pending') {

                /*
                 * STEP 11: Handle pending parent payments.
                 */

                if ($lockedSubscription->status === 'pending') {

                    /*
                     * An attempt already marked succeeded while
                     * its parent payment and subscription remain
                     * pending represents an inconsistent state.
                     *
                     * Do not silently activate it.
                     */
                    if (
                        $lockedAttempt->status === 'succeeded'
                        || $lockedAttempt->reconciliationStatus !== 'not_required'
                    ) {

                        $markAttemptSucceeded();

                        $recordReconciliation(
                            'pending_review',
                            sprintf(
                                'Successful payment attempt #%d (%s) requires manual reconciliation because the attempt and parent payment states are inconsistent.',
                                $lockedAttempt->attemptNumber,
                                $reference
                            )
                        );

                        $resultStatus = 'reconciliation_required';

                    } else {

                        /*
                         * Normal successful payment.
                         */
                        $markAttemptSucceeded();
                        $paymentUpdated = $this->paymentRepository
                            ->completeWithActivatedAttempt(
                                $lockedPayment->id,
                                $lockedAttempt->id
                            );

                        if (!$paymentUpdated) {
                            throw new Exception(
                                'Unable to complete subscription payment '
                                . 'and record its activation attempt.'
                            );
                        }

                        /*
                         * STEP 12: Calculate subscription dates.
                         */
                        $billingPeriod =
                            $lockedSubscription->billingPeriod;

                        if (
                            !in_array(
                                $billingPeriod,
                                ['monthly', 'yearly'],
                                true
                            )
                        ) {
                            throw new Exception(
                                'Subscription has an invalid billing period.'
                            );
                        }

                        $startDate = new \DateTimeImmutable(
                            'now',
                            new \DateTimeZone('UTC')
                        );

                        if ($billingPeriod === 'monthly') {

                            $endDate = $startDate
                                ->modify('first day of next month')
                                ->modify(
                                    '+' . (
                                        $startDate->format('j') - 1
                                    ) . ' days'
                                );

                            $nextMonth = $startDate
                                ->modify('first day of next month');

                            $daysInNextMonth = (int)
                            $nextMonth->format('t');

                            if (
                                (int) $startDate->format('j')
                                > $daysInNextMonth
                            ) {
                                $endDate = $nextMonth
                                    ->modify('last day of this month');
                            }

                        } else {

                            $endDate = $startDate->modify('+1 year');
                        }

                        $formattedStartDate =
                            $startDate->format('Y-m-d H:i:s');

                        $formattedEndDate =
                            $endDate->format('Y-m-d H:i:s');

                        /*
                         * STEP 13: Activate subscription.
                         */
                        $subscriptionUpdated =
                            $this->subscriptionRepository
                                ->activate(
                                    $lockedSubscription->id,
                                    $formattedStartDate,
                                    $formattedEndDate
                                );

                        if (!$subscriptionUpdated) {
                            throw new Exception(
                                'Unable to activate subscription.'
                            );
                        }

                        /*
                         * STEP 14: Record activation event.
                         */
                        $eventCreated =
                            $this->eventRepository->create(
                                $lockedSubscription->id,
                                $lockedSubscription->planVersionId,
                                $userId,
                                'SUBSCRIPTION_ACTIVATED',
                                'Subscription payment verified successfully and subscription activated.',
                                $userId
                            );

                        if (!$eventCreated) {
                            throw new Exception(
                                'Unable to record subscription activation event.'
                            );
                        }
                    }

                }
                elseif ($lockedSubscription->status === 'active') {

                    /*
                     * The subscription is already active.
                     *
                     * Record the successful charge but do not
                     * activate the subscription again.
                     */
                    $markAttemptSucceeded();

                    $paymentUpdated = $this->paymentRepository
                        ->updateStatus(
                            $lockedPayment->id,
                            'completed'
                        );

                    if (!$paymentUpdated) {
                        throw new Exception(
                            'Unable to update subscription payment status.'
                        );
                    }

                    $recordReconciliation(
                        'refund_required',
                        sprintf(
                            'Successful payment attempt #%d (%s) requires refund review because the subscription was already active.',
                            $lockedAttempt->attemptNumber,
                            $reference
                        )
                    );

                    $resultStatus = 'reconciliation_required';

                } else {

                    /*
                     * Successful payment, but subscription is no
                     * longer eligible for automatic activation.
                     */
                    $markAttemptSucceeded();

                    $paymentUpdated = $this->paymentRepository
                        ->updateStatus(
                            $lockedPayment->id,
                            'completed'
                        );

                    if (!$paymentUpdated) {
                        throw new Exception(
                            'Unable to update subscription payment status.'
                        );
                    }

                    $recordReconciliation(
                        'pending_review',
                        sprintf(
                            'Successful payment attempt #%d (%s) requires manual reconciliation because the subscription status is %s.',
                            $lockedAttempt->attemptNumber,
                            $reference,
                            $lockedSubscription->status
                        )
                    );

                    $resultStatus = 'reconciliation_required';
                }

            } else {

                /*
                 * STEP 15: Parent payment is no longer pending
                 * or completed.
                 *
                 * Paystack reports a successful charge, but the
                 * parent payment cannot safely be completed here.
                 */
                $markAttemptSucceeded();

                $recordReconciliation(
                    'pending_review',
                    sprintf(
                        'Successful payment attempt #%d (%s) requires manual reconciliation because the parent payment status is %s.',
                        $lockedAttempt->attemptNumber,
                        $reference,
                        $lockedPayment->status
                    )
                );

                $resultStatus = 'reconciliation_required';
            }

            /*
             * STEP 16: Commit all changes.
             */
            $db->commit();

        } catch (\Throwable $exception) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        /*
         * STEP 17: Reload committed records.
         */
        $updatedPayment = $this->paymentRepository
            ->findById($payment->id);

        $updatedSubscription = $this->subscriptionRepository
            ->findById($payment->subscriptionId);

        $updatedAttempt = $this->attemptRepository
            ->findByReference($reference);

        if (
            $updatedPayment === null
            || $updatedSubscription === null
            || $updatedAttempt === null
        ) {
            throw new Exception(
                'Payment was processed, but updated records could not be retrieved.'
            );
        }

        return [
            'subscription' => $updatedSubscription,
            'payment' => $updatedPayment,
            'attempt' => $updatedAttempt,
            'reference' => $reference,
            'status' => $resultStatus,
            'reconciliation_status' =>
                $updatedAttempt->reconciliationStatus
        ];
    }


    /**
     * Resume payment for an existing pending subscription.
     *
     * Each Paystack checkout receives its own durable payment attempt.
     * The parent subscription payment reference is never overwritten.
     */
    public function resumePendingSubscriptionPayment(
        int $userId
    ): array {

        /*
         * STEP 1: Find the candidate pending subscription.
         */
        $subscription = $this->subscriptionRepository
            ->findPendingByUserId($userId);

        if ($subscription === null) {
            throw new Exception('No pending subscription found.');
        }

        if ($subscription->userId !== $userId) {
            throw new Exception(
                'You are not authorized to resume this payment.'
            );
        }

        /*
         * STEP 2: Find its pending parent payment.
         */
        $payments = $this->paymentRepository
            ->findBySubscriptionId($subscription->id);

        $payment = null;

        foreach ($payments as $candidate) {
            if ($candidate->status === 'pending') {
                $payment = $candidate;
                break;
            }
        }

        if ($payment === null) {
            throw new Exception(
                'No pending payment was found for this subscription.'
            );
        }

        /*
         * STEP 3: Validate the authenticated user's account.
         */
        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            throw new Exception('User account not found.');
        }

        if (empty($user->email)) {
            throw new Exception(
                'User email address is required for payment.'
            );
        }

        /*
         * STEP 4: Lock and validate the payment and subscription.
         *
         * Lock order:
         * 1. Parent payment.
         * 2. Subscription.
         *
         * All attempt creation decisions happen while holding
         * the parent payment lock.
         */
        $db = \App\Helpers\DatabaseManager::getConnection(
            'subscription_db'
        );

        $attempt = null;
        $existingAttempt = null;

        try {
            $db->beginTransaction();

            $lockedPayment = $this->paymentRepository
                ->findByIdForUpdate($payment->id);

            if ($lockedPayment === null) {
                throw new Exception(
                    'Subscription payment could not be found.'
                );
            }

            $lockedSubscription = $this->subscriptionRepository
                ->findByIdForUpdate($lockedPayment->subscriptionId);

            if ($lockedSubscription === null) {
                throw new Exception(
                    'Subscription associated with this payment was not found.'
                );
            }

            if ($lockedSubscription->userId !== $userId) {
                throw new Exception(
                    'You are not authorized to resume this payment.'
                );
            }

            if ($lockedPayment->status !== 'pending') {
                throw new Exception(
                    'This payment is no longer pending.'
                );
            }

            if ($lockedSubscription->status !== 'pending') {
                throw new Exception(
                    'This subscription is no longer pending.'
                );
            }

            if (
                !in_array(
                    $lockedSubscription->billingPeriod,
                    ['monthly', 'yearly'],
                    true
                )
            ) {
                throw new Exception(
                    'Pending subscription has an invalid billing period.'
                );
            }

            /*
             * STEP 5: Check for an existing active attempt.
             */
            $existingAttempt = $this->attemptRepository
                ->findActiveByPaymentId($lockedPayment->id);

            if ($existingAttempt !== null) {

                if ($existingAttempt->status === 'initializing') {
                    throw new Exception(
                        'A payment checkout is already being initialized. Please try again shortly.'
                    );
                }

                if (
                    $existingAttempt->status ===
                    'initialization_unknown'
                ) {
                    throw new Exception(
                        'The previous payment initialization outcome is unknown. Manual reconciliation is required before another attempt.'
                    );
                }

                if (
                    $existingAttempt->status === 'pending' &&
                    !empty($existingAttempt->authorizationUrl)
                ) {
                    $db->commit();

                    return [
                        'subscription' => $lockedSubscription,
                        'payment' => $lockedPayment,
                        'amount' => $lockedPayment->amount,
                        'currency' => $lockedPayment->currency,
                        'reference' => $existingAttempt->reference,
                        'authorization_url' =>
                            $existingAttempt->authorizationUrl,
                        'access_code' =>
                            $existingAttempt->accessCode
                    ];
                }

                throw new Exception(
                    'An active payment attempt exists, but its checkout information is unavailable. Manual investigation is required.'
                );
            }

            /*
             * STEP 6: Create a durable attempt before contacting Paystack.
             */
            $newReference = $this->paystackService
                ->generateReference();

            $attemptNumber = $this->attemptRepository
                ->findNextAttemptNumber($lockedPayment->id);

            $attempt = $this->attemptRepository->create(
                $lockedPayment->id,
                $attemptNumber,
                $newReference
            );

            if ($attempt === null) {
                throw new Exception(
                    'Unable to create a subscription payment attempt.'
                );
            }

            /*
             * Commit before making the external Paystack request.
             */
            $db->commit();

        } catch (\Throwable $exception) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        /*
         * STEP 7: Initialize Paystack outside the database transaction.
         */
        try {
            $paystackTransaction = $this->paystackService
                ->initializeTransaction(
                    $user->email,
                    (float) $lockedPayment->amount,
                    $attempt->reference,
                    [
                        'type' => 'subscription',
                        'user_id' => $userId,
                        'subscription_id' =>
                            $lockedSubscription->id,
                        'plan_id' =>
                            $lockedSubscription->planId,
                        'plan_version_id' =>
                            $lockedSubscription->planVersionId,
                        'billing_period' =>
                            $lockedSubscription->billingPeriod,
                        'payment_attempt_id' =>
                            $attempt->id,
                        'attempt_number' =>
                            $attempt->attemptNumber
                    ]
                );

        } catch (\Throwable $exception) {

            /*
             * Generic exceptions are treated conservatively:
             * the provider may have received the request.
             */
            $outcomeUnknown = !(
                    $exception instanceof PaystackInitializationException
                ) || $exception->outcomeUnknown;

            $newStatus = $outcomeUnknown
                ? 'initialization_unknown'
                : 'failed';

            try {
                $this->attemptRepository->updateStatus(
                    $attempt->id,
                    $newStatus,
                    $exception->getMessage()
                );
            } catch (\Throwable $stateException) {
                error_log(
                    'Unable to update subscription payment attempt ' .
                    $attempt->id . ': ' .
                    $stateException->getMessage()
                );
            }

            throw $exception;
        }

        /*
         * STEP 8: Validate the Paystack response.
         *
         * A malformed successful response is treated as unknown,
         * because Paystack may already have created the transaction.
         */
        if (
            empty($paystackTransaction['reference']) ||
            $paystackTransaction['reference'] !== $attempt->reference ||
            empty($paystackTransaction['authorization_url'])
        ) {
            $reason = 'Paystack returned incomplete or invalid checkout information.';

            $this->attemptRepository->updateStatus(
                $attempt->id,
                'initialization_unknown',
                $reason
            );

            throw new Exception($reason);
        }

        /*
         * STEP 9: Persist the checkout details.
         *
         * access_code is optional.
         */
        $checkoutSaved = $this->attemptRepository
            ->updateCheckoutDetails(
                $attempt->id,
                $paystackTransaction['authorization_url'],
                $paystackTransaction['access_code'] ?? null
            );

        if (!$checkoutSaved) {

            $reason = 'Paystack initialized the transaction, but checkout details could not be saved. Reconciliation is required.';

            $this->attemptRepository->updateStatus(
                $attempt->id,
                'initialization_unknown',
                $reason
            );

            throw new Exception($reason);
        }

        /*
         * STEP 10: Reload the parent payment.
         *
         * Its original reference must remain unchanged.
         */
        $updatedPayment = $this->paymentRepository
            ->findById($lockedPayment->id);

        $updatedSubscription = $this->subscriptionRepository
            ->findById($lockedSubscription->id);

        if (
            $updatedPayment === null ||
            $updatedSubscription === null
        ) {
            throw new Exception(
                'Checkout was initialized, but subscription records could not be retrieved.'
            );
        }

        $updatedAttempt = $this->attemptRepository
            ->findById($attempt->id);

        if ($updatedAttempt === null) {
            throw new Exception(
                'Checkout was initialized, but the payment attempt could not be retrieved.'
            );
        }

        /*
         * STEP 11: Return the new attempt reference.
         */
        return [
            'subscription' => $updatedSubscription,
            'payment' => $updatedPayment,
            'amount' => $updatedPayment->amount,
            'currency' => $updatedPayment->currency,
            'reference' => $updatedAttempt->reference,
            'authorization_url' =>
                $updatedAttempt->authorizationUrl,
            'access_code' =>
                $updatedAttempt->accessCode
        ];
    }


    /**
     * Calculate yearly subscription price
     * from an approved pricing version.
     */
    public function calculateYearlyPrice(
        int $planVersionId
    ): float {
        $version =
            $this->planVersionRepository
                ->findById($planVersionId);

        if (!$version) {
            throw new Exception(
                'Pricing version not found.'
            );
        }

        if ($version->status !== 'approved') {
            throw new Exception(
                'Only approved pricing can be used.'
            );
        }

        $annualBase =
            $version->monthlyPrice * 12;

        $discount =
            $annualBase *
            ($version->yearlyDiscountPercent / 100);

        return round(
            $annualBase - $discount,
            2
        );
    }


    /**
     * Resolve the subscription beneficiary from the authenticated user
     * and selected plan.
     *
     * The frontend must never determine beneficiary identity.
     */
    private function resolveSubscriptionBeneficiary(
        int $userId,
        \App\Models\Plan $plan,
        ?int $shopId = null
    ): array {
        switch ($plan->role) {
            case 'BARBER':
                if ($plan->scope !== 'HOME') {
                    throw new Exception(
                        'Barber subscriptions must use the HOME scope.'
                    );
                }

                if ($shopId !== null) {
                    throw new Exception(
                        'shop_id is not applicable to a barber HOME subscription.'
                    );
                }

                $barber = $this->barberRepository->findByUserId(
                    $userId
                );

                if (!$barber) {
                    throw new Exception(
                        'Barber profile not found.'
                    );
                }

                if (
                    $barber->approvalStatus !== 'approved' ||
                    $barber->status !== 'active'
                ) {
                    throw new Exception(
                        'Your barber profile must be approved and active.'
                    );
                }

                return [
                    'scope' => 'HOME',
                    'beneficiary_type' => 'BARBER',
                    'beneficiary_id' => $barber->id,
                ];

            case 'SHOP_OWNER':
                if ($plan->scope !== 'SHOP') {
                    throw new Exception(
                        'Shop owner subscriptions must use the SHOP scope.'
                    );
                }

                if ($shopId === null) {
                    throw new Exception(
                        'shop_id is required for a shop subscription.'
                    );
                }

                $shop = $this->shopRepository->findById(
                    $shopId
                );

                if (!$shop || $shop->ownerId !== $userId) {
                    throw new Exception(
                        'The selected shop does not belong to your account.'
                    );
                }

                if (
                    $shop->approvalStatus !== 'approved' ||
                    $shop->status !== 'active'
                ) {
                    throw new Exception(
                        'The selected shop must be approved and active.'
                    );
                }

                return [
                    'scope' => 'SHOP',
                    'beneficiary_type' => 'SHOP',
                    'beneficiary_id' => $shop->id,
                ];

            case 'VENDOR':
                if ($plan->scope !== 'VENDOR') {
                    throw new Exception(
                        'Vendor subscriptions must use the VENDOR scope.'
                    );
                }

                if ($shopId !== null) {
                    throw new Exception(
                        'shop_id is not applicable to a vendor subscription.'
                    );
                }

                return [
                    'scope' => 'VENDOR',
                    'beneficiary_type' => 'VENDOR',
                    'beneficiary_id' => $userId,
                ];

            default:
                throw new Exception(
                    'Unsupported subscription plan role.'
                );
        }
    }



}