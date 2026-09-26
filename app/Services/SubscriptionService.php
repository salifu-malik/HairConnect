<?php

namespace App\Services;

use App\Repositories\PlanRepository;
use App\Repositories\PlanVersionRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\SubscriptionPaymentRepository;
use App\Repositories\UserRepository;
use Exception;

class SubscriptionService
{
    private PlanRepository $planRepository;
    private PlanVersionRepository $planVersionRepository;
    private SubscriptionRepository $subscriptionRepository;
    private SubscriptionPaymentRepository $paymentRepository;
    private UserRepository $userRepository;

    public function __construct(
        PlanRepository $planRepository,
        PlanVersionRepository $planVersionRepository,
        SubscriptionRepository $subscriptionRepository,
        SubscriptionPaymentRepository $paymentRepository,
        UserRepository $userRepository
    ) {
        $this->planRepository = $planRepository;
        $this->planVersionRepository = $planVersionRepository;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->paymentRepository = $paymentRepository;
        $this->userRepository = $userRepository;
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
            throw new Exception(
                'Unable to create pricing proposal.'
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

        return $this->planVersionRepository
            ->updateApprovalStatus(
                $planVersionId,
                $approvalStatus,
                $adminId,
                $rejectionReason
            );
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