<?php

namespace App\Services;

use App\Repositories\PlanRepository;
use App\Repositories\PlanVersionRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\SubscriptionPaymentRepository;
use App\Repositories\UserRepository;
use App\Repositories\SubscriptionEventRepository;
use Exception;

class SubscriptionService
{
    private PlanRepository $planRepository;
    private PlanVersionRepository $planVersionRepository;
    private SubscriptionRepository $subscriptionRepository;
    private SubscriptionPaymentRepository $paymentRepository;
    private UserRepository $userRepository;
    private SubscriptionEventRepository $eventRepository;
    private PaystackService $paystackService;
    public function __construct(
        PlanRepository $planRepository,
        PlanVersionRepository $planVersionRepository,
        SubscriptionRepository $subscriptionRepository,
        SubscriptionPaymentRepository $paymentRepository,
        UserRepository $userRepository,
        SubscriptionEventRepository $eventRepository,
         PaystackService $paystackService
    ) {
        $this->planRepository = $planRepository;
        $this->planVersionRepository = $planVersionRepository;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->paymentRepository = $paymentRepository;
        $this->userRepository = $userRepository;
        $this->eventRepository = $eventRepository;
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
        string $billingPeriod
    ): array {
        // Get the selected plan.
        $plan = $this->planRepository->findById($planId);

        if (!$plan) {
            throw new Exception(
                'Subscription plan not found.'
            );
        }

        // Make sure the user has the role required
        // by the selected subscription plan.
        $this->validateSubscriberRole(
            $userId,
            $plan->role
        );

        // Get the currently approved pricing version.
        $pricing = $this->getApprovedPricing(
            $planId
        );

        // Calculate the authoritative subscription amount.
        $amount = $this->calculateSubscriptionAmount(
            $pricing->id,
            $billingPeriod
        );

        // Prevent another pending subscription
        // for the same user.
        $existingPending =
            $this->subscriptionRepository
                ->findPendingByUserId($userId);

        if ($existingPending !== null) {
            throw new Exception(
                'You already have a pending subscription.'
            );
        }

        // Prevent subscribing while an active
        // subscription already exists.
        $existingActive =
            $this->subscriptionRepository
                ->findActiveByUserId($userId);

        if ($existingActive !== null) {
            throw new Exception(
                'You already have an active subscription.'
            );
        }

        // Get the authenticated user's email.
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new Exception(
                'User not found.'
            );
        }

        if (empty($user->email)) {
            throw new Exception(
                'User email is required for subscription payment.'
            );
        }

        /*
         * Generate the payment reference on the backend.
         * The frontend must never generate the authoritative
         * Paystack reference.
         */
        $reference =
            $this->paystackService
                ->generateReference();

        /*
         * Initialize the Paystack transaction first.
         *
         * The amount comes from the approved pricing version,
         * not from the frontend.
         */
        $paystackTransaction =
            $this->paystackService
                ->initializeTransaction(
                    $user->email,
                    $amount,
                    $reference,
                    [
                        'type' => 'subscription',
                        'user_id' => $userId,
                        'plan_id' => $planId,
                        'plan_version_id' => $pricing->id,
                        'billing_period' => $billingPeriod
                    ]
                );

        if (
            empty($paystackTransaction['reference'])
        ) {
            throw new Exception(
                'Paystack did not return a transaction reference.'
            );
        }

        /*
         * Use the reference returned by Paystack.
         */
        $reference =
            $paystackTransaction['reference'];

        $startDate = date('Y-m-d H:i:s');

        if ($billingPeriod === 'monthly') {
            $endDate = date(
                'Y-m-d H:i:s',
                strtotime('+1 month')
            );
        } else {
            $endDate = date(
                'Y-m-d H:i:s',
                strtotime('+1 year')
            );
        }

        // Create the subscription as pending.
        $subscription =
            $this->subscriptionRepository->create(
                $userId,
                $planId,
                $pricing->id,
                $startDate,
                $endDate
            );

        if ($subscription === null) {
            throw new Exception(
                'Unable to create subscription.'
            );
        }

        /*
         * Create the corresponding pending payment.
         */
        $payment =
            $this->paymentRepository->create(
                $subscription->id,
                $amount,
                'GHS',
                $reference
            );

        if ($payment === null) {
            throw new Exception(
                'Subscription was created, but the payment record could not be created.'
            );
        }

        // Record the subscription creation event.
        $eventCreated =
            $this->eventRepository->create(
                $subscription->id,
                $pricing->id,
                $userId,
                'SUBSCRIPTION_CREATED',
                'Pending ' .
                $billingPeriod .
                ' subscription created for plan ID ' .
                $planId .
                '.',
                $userId
            );

        if (!$eventCreated) {
            throw new Exception(
                'Subscription was created, but the audit event could not be recorded.'
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
            'authorization_url' =>
                $paystackTransaction['authorization_url']
                ?? null,
            'access_code' =>
                $paystackTransaction['access_code']
                ?? null
        ];
    }




    /**
     * Verify a subscription payment with Paystack
     * and activate the subscription only after successful
     * server-side verification.
     */
    public function verifySubscriptionPayment(
        string $reference,
        int $userId
    ): array {
        // Find the HairConnect payment using the reference.
        $payment =
            $this->paymentRepository
                ->findByReference($reference);

        if ($payment === null) {
            throw new Exception(
                'Subscription payment not found.'
            );
        }

        // Make sure the payment belongs to the
        // authenticated user.
        $subscription =
            $this->subscriptionRepository
                ->findById(
                    $payment->subscriptionId
                );

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

        // Do not process an already completed payment.
        if ($payment->status === 'completed') {
            throw new Exception(
                'This payment has already been completed.'
            );
        }

        // The subscription must still be pending.
        if ($subscription->status !== 'pending') {
            throw new Exception(
                'This subscription is no longer pending.'
            );
        }

        /*
         * Ask Paystack to verify the transaction.
         *
         * This is server-to-server verification.
         */
        $transaction =
            $this->paystackService
                ->verifyTransaction($reference);

        // Verify the reference returned by Paystack.
        if (
            ($transaction['reference'] ?? '')
            !== $payment->reference
        ) {
            throw new Exception(
                'Paystack transaction reference does not match the payment.'
            );
        }

        // Paystack must report a successful transaction.
        if (
            ($transaction['status'] ?? '')
            !== 'success'
        ) {
            throw new Exception(
                'Payment was not successful.'
            );
        }

        // Verify the currency.
        if (
            strtoupper(
                $transaction['currency'] ?? ''
            )
            !== strtoupper($payment->currency)
        ) {
            throw new Exception(
                'Payment currency does not match the subscription payment.'
            );
        }

        /*
         * Paystack returns the amount in the smallest
         * currency unit.
         *
         * HairConnect stores GHS amounts as normal
         * decimal amounts.
         */
        $expectedAmount =
            (int) round(
                $payment->amount * 100
            );

        $paidAmount =
            (int) (
                $transaction['amount'] ?? 0
            );

        if ($paidAmount !== $expectedAmount) {
            throw new Exception(
                'Payment amount does not match the subscription amount.'
            );
        }

        /*
         * Everything has passed verification.
         *
         * Mark the payment as completed first.
         */
        $paymentUpdated =
            $this->paymentRepository
                ->updateStatus(
                    $payment->id,
                    'completed'
                );

        if (!$paymentUpdated) {
            throw new Exception(
                'Unable to update subscription payment status.'
            );
        }

        /*
         * Activate the subscription.
         */
        $subscriptionUpdated =
            $this->subscriptionRepository
                ->updateStatus(
                    $subscription->id,
                    'active'
                );

        if (!$subscriptionUpdated) {
            throw new Exception(
                'Payment was completed, but the subscription could not be activated.'
            );
        }

        /*
         * Record the subscription renewal/activation
         * event in the audit trail.
         */
        $eventCreated =
            $this->eventRepository->create(
                $subscription->id,
                $subscription->planVersionId,
                $userId,
                'SUBSCRIPTION_RENEWED',
                'Subscription payment verified successfully and subscription activated.',
                $userId
            );

        if (!$eventCreated) {
            throw new Exception(
                'Subscription was activated, but the audit event could not be recorded.'
            );
        }

        // Reload the updated records.
        $updatedPayment =
            $this->paymentRepository
                ->findById($payment->id);

        $updatedSubscription =
            $this->subscriptionRepository
                ->findById($subscription->id);

        return [
            'subscription' =>
                $updatedSubscription,

            'payment' =>
                $updatedPayment,

            'reference' =>
                $reference,

            'status' =>
                'success'
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

}