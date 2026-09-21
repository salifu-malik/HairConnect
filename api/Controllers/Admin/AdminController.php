<?php

namespace Api\Controllers\Admin;

use App\Helpers\JwtHelper;
use App\Repositories\ShopRepository;
use App\Repositories\UserRepository;
use App\Services\AdminService;
use Exception;

class AdminController
{
    private AdminService $adminService;

    public function __construct()
    {
        $this->adminService = new AdminService(
            new ShopRepository(),
            new UserRepository()
        );
    }

    /**
     * GET /api/admin/shops/pending
     */
    public function getPendingShops(): void
    {
        try {
            $adminId = $this->getAuthenticatedUserId();

            $shops = $this->adminService->getPendingShops($adminId);

            $data = [];

            foreach ($shops as $shop) {
                $data[] = [
                    'id' => $shop->id,
                    'owner_id' => $shop->ownerId,
                    'name' => $shop->name,
                    'location' => $shop->location,
                    'description' => $shop->description,
                    'approval_status' => $shop->approvalStatus,
                    'status' => $shop->status,
                    'approved_at' => $shop->approvedAt,
                    'approved_by' => $shop->approvedBy,
                ];
            }

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(403);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * PATCH /api/admin/shops/{id}/approval
     */
    public function updateShopApproval(int $shopId): void
    {
        try {
            $adminId = $this->getAuthenticatedUserId();

            $input = json_decode(
                file_get_contents('php://input'),
                true
            );

            $approvalStatus = $input['approval_status'] ?? null;

            if (!$approvalStatus) {
                throw new Exception(
                    'approval_status is required.'
                );
            }

            $this->adminService->updateShopApproval(
                $adminId,
                $shopId,
                $approvalStatus
            );

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'message' => 'Shop approval status updated successfully.'
            ]);

        } catch (Exception $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Extract authenticated user ID from JWT.
     */
    private function getAuthenticatedUserId(): int
    {
        $headers = getallheaders();

        $authorization = $headers['Authorization']
            ?? $headers['authorization']
            ?? null;

        if (!$authorization) {
            throw new Exception('Authorization token required.');
        }

        if (!preg_match('/Bearer\s+(.+)/i', $authorization, $matches)) {
            throw new Exception('Invalid authorization header.');
        }

        $token = trim($matches[1]);

        $payload = JwtHelper::decode($token);

        if (!$payload || !isset($payload['user_id'])) {
            throw new Exception('Invalid or expired token.');
        }

        return (int) $payload['user_id'];
    }
}