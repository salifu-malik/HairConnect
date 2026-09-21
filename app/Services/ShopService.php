<?php

namespace App\Services;

use App\Repositories\ShopRepository;
use App\Repositories\UserRepository;
use Exception;

class ShopService
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

    /**
     * Create a new shop for the authenticated user.
     */
    public function createShop(
        int $ownerId,
        array $data
    ): int {
        /*
         * ---------------------------------------------------------
         * 1. Validate authenticated user
         * ---------------------------------------------------------
         */

        if ($ownerId <= 0) {
            throw new Exception(
                'Invalid authenticated user.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 2. Verify that the user exists
         * ---------------------------------------------------------
         */

        $user = $this->userRepository->findById($ownerId);

        if (!$user) {
            throw new Exception(
                'User not found.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 3. Verify SHOP_OWNER role
         * ---------------------------------------------------------
         */

        $roles = $this->userRepository->getRoles($ownerId);

        $isShopOwner = in_array(
            'SHOP_OWNER',
            $roles,
            true
        );

        if (!$isShopOwner) {
            throw new Exception(
                'Only users with the SHOP_OWNER role can create a shop.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 4. Validate shop information
         * ---------------------------------------------------------
         */

        $name = trim(
            (string) ($data['name'] ?? '')
        );

        $location = trim(
            (string) ($data['location'] ?? '')
        );

        $description = isset($data['description'])
            ? trim((string) $data['description'])
            : null;

        if ($name === '') {
            throw new Exception(
                'Shop name is required.'
            );
        }

        if (mb_strlen($name) > 255) {
            throw new Exception(
                'Shop name cannot exceed 255 characters.'
            );
        }

        if ($location === '') {
            throw new Exception(
                'Shop location is required.'
            );
        }

        if (mb_strlen($location) > 255) {
            throw new Exception(
                'Shop location cannot exceed 255 characters.'
            );
        }

        if (
            $description !== null &&
            mb_strlen($description) > 5000
        ) {
            throw new Exception(
                'Shop description is too long.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 5. Create shop
         * ---------------------------------------------------------
         *
         * ShopRepository automatically creates the shop as:
         *
         * approval_status = pending
         * status = inactive
         */

        return $this->shopRepository->create([
            'owner_id' => $ownerId,
            'name' => $name,
            'location' => $location,
            'description' => $description,
        ]);
    }

    /**
     * Get shops belonging to the authenticated shop owner.
     */
    public function getMyShops(int $ownerId): array
    {
        if ($ownerId <= 0) {
            throw new Exception(
                'Invalid authenticated user.'
            );
        }

        $user = $this->userRepository->findById($ownerId);

        if (!$user) {
            throw new Exception(
                'User not found.'
            );
        }

        $roles = $this->userRepository->getRoles($ownerId);

        if (!in_array('SHOP_OWNER', $roles, true)) {
            throw new Exception(
                'Only shop owners can access their shops.'
            );
        }

        return $this->shopRepository->findByOwnerId(
            $ownerId
        );
    }

    /**
     * Get shops that barbers are allowed to apply to.
     */
    public function getAvailableShops(): array
    {
        return $this->shopRepository
            ->findApprovedActiveShops();
    }
}