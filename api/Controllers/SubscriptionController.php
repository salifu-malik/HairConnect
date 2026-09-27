<?php

namespace Api\Controllers;

use App\Helpers\JwtHelper;
use App\Services\SubscriptionService;
use Exception;

class SubscriptionController
{
    private SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Get the authenticated user's ID from the JWT.
     */
    private function getAuthenticatedUserId(): int
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            throw new Exception('Authorization token is required.');
        }

        $token = trim($matches[1]);

        $payload = JwtHelper::decode($token);

        if (
            !is_array($payload) ||
            !isset($payload['user_id']) ||
            !is_numeric($payload['user_id'])
        ) {
            throw new Exception('Invalid authentication token.');
        }

        return (int) $payload['user_id'];
    }

    /**
     * Create a pending subscription and initialize Paystack payment.
     *
     * Expected JSON:
     * {
     *     "plan_id": 1,
     *     "billing_period": "monthly"
     * }
     */
    public function create(): void
    {
        header('Content-Type: application/json');

        try {
            $userId = $this->getAuthenticatedUserId();

            $input = json_decode(
                file_get_contents('php://input'),
                true
            );

            if (!is_array($input)) {
                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid request body.'
                ]);

                return;
            }

            $planId = isset($input['plan_id'])
                ? (int) $input['plan_id']
                : 0;

            $billingPeriod = strtolower(
                trim($input['billing_period'] ?? '')
            );

            if ($planId <= 0) {
                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'message' => 'plan_id is required.'
                ]);

                return;
            }

            if (!in_array($billingPeriod, ['monthly', 'yearly'], true)) {
                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'message' => 'billing_period must be monthly or yearly.'
                ]);

                return;
            }

            $result = $this->subscriptionService->createSubscription(
                $userId,
                $planId,
                $billingPeriod
            );

            http_response_code(201);

            echo json_encode([
                'success' => true,
                'message' => 'Subscription initialized successfully.',
                'data' => [
                    'subscription' => $result['subscription'],
                    'payment' => $result['payment'],
                    'amount' => $result['amount'],
                    'currency' => $result['currency'],
                    'billing_period' => $result['billing_period'],
                    'plan_version_id' => $result['plan_version_id'],
                    'reference' => $result['reference'],
                    'authorization_url' => $result['authorization_url'],
                    'access_code' => $result['access_code']
                ]
            ]);

        } catch (Exception $e) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }


    /**
     * Verify a subscription payment with Paystack.
     *
     * Expected JSON:
     * {
     *     "reference": "HC_SUB_..."
     * }
     */
    public function verify(): void
    {
        header('Content-Type: application/json');

        try {
            $userId = $this->getAuthenticatedUserId();

            $input = json_decode(
                file_get_contents('php://input'),
                true
            );

            if (!is_array($input)) {
                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid request body.'
                ]);

                return;
            }

            $reference = trim(
                $input['reference'] ?? ''
            );

            if ($reference === '') {
                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'message' => 'Payment reference is required.'
                ]);

                return;
            }

            $result = $this->subscriptionService
                ->verifySubscriptionPayment(
                    $reference,
                    $userId
                );

            http_response_code(200);

            echo json_encode([
                'success' => true,
                'message' => 'Subscription payment verified successfully.',
                'data' => [
                    'subscription' => $result['subscription'],
                    'payment' => $result['payment'],
                    'reference' => $result['reference'],
                    'status' => $result['status']
                ]
            ]);

        } catch (Exception $e) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }


    public function getCurrent(): void
    {
        header('Content-Type: application/json');

        try {
            $userId = $this->getAuthenticatedUserId();

            $result = $this->subscriptionService
                ->getCurrentSubscription($userId);

            http_response_code(200);

            echo json_encode([
                'success' => true,
                'message' => 'Current subscription retrieved successfully.',
                'data' => $result
            ]);

        } catch (Exception $e) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }


    /**
     * Get the currently approved pricing for a subscription plan.
     *
     * Expected query parameter:
     * ?plan_id=1
     */
    public function getPricing(): void
    {
        header('Content-Type: application/json');

        try {
            $planId = isset($_GET['plan_id'])
                ? (int) $_GET['plan_id']
                : 0;

            if ($planId <= 0) {
                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'message' => 'plan_id is required.'
                ]);

                return;
            }

            $version = $this->subscriptionService
                ->getApprovedPricing($planId);

            $yearlyPrice = $this->subscriptionService
                ->calculateYearlyPrice($version->id);

            http_response_code(200);

            echo json_encode([
                'success' => true,
                'message' => 'Approved subscription pricing retrieved successfully.',
                'data' => [
                    'plan_id' => $version->planId,
                    'plan_version_id' => $version->id,
                    'monthly_price' => $version->monthlyPrice,
                    'yearly_discount_percent' =>
                        $version->yearlyDiscountPercent,
                    'yearly_price' => $yearlyPrice,
                    'currency' => 'GHS'
                ]
            ]);

        } catch (Exception $e) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get pending subscription pricing proposals for Admin review.
     */
    public function getPendingPricing(): void
    {
        header('Content-Type: application/json');

        try {
            $adminId = $this->getAuthenticatedUserId();

            $pendingPricing = $this->subscriptionService
                ->getPendingPricing($adminId);

            http_response_code(200);

            echo json_encode([
                'success' => true,
                'message' => 'Pending subscription pricing retrieved successfully.',
                'data' => $pendingPricing
            ]);

        } catch (Exception $e) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Approve or reject a pending subscription pricing proposal.
     *
     * Expected JSON:
     * {
     *     "plan_version_id": 1,
     *     "approval_status": "approved"
     * }
     *
     * For rejection:
     * {
     *     "plan_version_id": 1,
     *     "approval_status": "rejected",
     *     "rejection_reason": "Reason for rejection"
     * }
     */
    public function updatePricingApproval(): void
    {
        header('Content-Type: application/json');

        try {
            $adminId = $this->getAuthenticatedUserId();

            $input = json_decode(
                file_get_contents('php://input'),
                true
            );

            if (!is_array($input)) {
                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid request body.'
                ]);

                return;
            }

            $planVersionId = isset($input['plan_version_id'])
                ? (int) $input['plan_version_id']
                : 0;

            $approvalStatus = strtolower(
                trim($input['approval_status'] ?? '')
            );

            $rejectionReason = isset($input['rejection_reason'])
                ? trim($input['rejection_reason'])
                : null;

            if ($planVersionId <= 0) {
                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'message' => 'plan_version_id is required.'
                ]);

                return;
            }

            if (!in_array(
                $approvalStatus,
                ['approved', 'rejected'],
                true
            )) {
                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'message' =>
                        'approval_status must be approved or rejected.'
                ]);

                return;
            }

            if (
                $approvalStatus === 'rejected' &&
                ($rejectionReason === null || $rejectionReason === '')
            ) {
                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'message' => 'A rejection reason is required.'
                ]);

                return;
            }

            $result = $this->subscriptionService
                ->updatePricingApproval(
                    $adminId,
                    $planVersionId,
                    $approvalStatus,
                    $rejectionReason
                );

            if (!$result) {
                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'message' => 'Unable to update pricing approval.'
                ]);

                return;
            }

            http_response_code(200);

            echo json_encode([
                'success' => true,
                'message' => $approvalStatus === 'approved'
                    ? 'Subscription pricing approved successfully.'
                    : 'Subscription pricing rejected successfully.'
            ]);

        } catch (Exception $e) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

}