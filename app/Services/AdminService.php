<?php

namespace App\Services;

use App\Repositories\ShopRepository;
use App\Repositories\UserRepository;
use Exception;

class AdminService
{
    private ShopRepository $shopRepository;
    private UserRepository $userRepository;

    public function __construct(
        ShopRepository $shopRepository,
        UserRepository $userRepository
    ) {
        $this->shopRepository = $shopRepository;
        $this->userRepository = $userRepository;
    }


     //Make sure the authenticated user is an ADMIN.
    public function validateAdmin(int $adminId): void
    {
        $user = $this->userRepository->findById($adminId);

        if (!$user) {
            throw new Exception('Admin user not found.');
        }

        $roles = $this->userRepository->getRoles($adminId);

        if (!in_array('ADMIN', $roles, true)) {
            throw new Exception('Access denied. Admin privileges required.');
        }
    }


     //Get all shops waiting for admin approval.
    public function getPendingShops(int $adminId): array
    {
        $this->validateAdmin($adminId);

        return $this->shopRepository->findPendingShops();
    }


     //Approve or reject a shop.
    public function updateShopApproval(
        int $adminId,
        int $shopId,
        string $approvalStatus
    ): bool {
        $this->validateAdmin($adminId);

        $shop = $this->shopRepository->findById($shopId);

        if (!$shop) {
            throw new Exception('Shop not found.');
        }

        if ($shop->approvalStatus !== 'pending') {
            throw new Exception(
                'Only pending shops can be approved or rejected.'
            );
        }

        if (!in_array($approvalStatus, ['approved', 'rejected'], true)) {
            throw new Exception(
                'Invalid approval status. Use approved or rejected.'
            );
        }

        return $this->shopRepository->updateApprovalStatus(
            $shopId,
            $approvalStatus,
            $adminId
        );
    }
}