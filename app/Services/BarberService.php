<?php

namespace App\Services;

use App\Repositories\BarberRepository;
use App\Repositories\ShopRepository;
use App\Repositories\UserRepository;
use Exception;

class BarberService
{
    private BarberRepository $barberRepository;
    private ShopRepository $shopRepository;
    private UserRepository $userRepository;

    public function __construct(
        BarberRepository $barberRepository,
        ShopRepository $shopRepository,
        UserRepository $userRepository
    ) {
        $this->barberRepository = $barberRepository;
        $this->shopRepository = $shopRepository;
        $this->userRepository = $userRepository;
    }

//    Register a babrber without a shop(Indepent Barber)
    public function registerIndependentBarber(
        int $userId,
        array $data
    ): int {
        if ($userId <= 0) {
            throw new Exception('Invalid authenticated user.');
        }

        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new Exception('User not found.');
        }

        $roles = $this->userRepository->getRoles($userId);

        if (!in_array('BARBER', $roles, true)) {
            throw new Exception(
                'Only users with the BARBER role can create a barber profile.'
            );
        }

        $existingBarber =
            $this->barberRepository->findByUserId($userId);

        if ($existingBarber) {
            throw new Exception(
                'This user already has a barber profile.'
            );
        }

        $experience = (int) ($data['experience'] ?? 0);

        if ($experience < 0) {
            throw new Exception(
                'Experience cannot be negative.'
            );
        }

        $speciality = isset($data['speciality'])
            ? trim((string) $data['speciality'])
            : null;

        if ($speciality === '') {
            $speciality = null;
        }

        if ($speciality !== null && mb_strlen($speciality) > 255) {
            throw new Exception(
                'Speciality cannot exceed 255 characters.'
            );
        }

        return $this->barberRepository->createIndependent([
            'user_id' => $userId,
            'experience' => $experience,
            'speciality' => $speciality,
        ]);
    }

    /**
     * Get all barbers belonging to the authenticated shop owner.
     */
    public function getMyBarbers(int $ownerId): array
    {
        if ($ownerId <= 0) {
            throw new Exception('Invalid authenticated user.');
        }

        $user = $this->userRepository->findById($ownerId);

        if (!$user) {
            throw new Exception('User not found.');
        }

        $roles = $this->userRepository->getRoles($ownerId);

        if (!in_array('SHOP_OWNER', $roles, true)) {
            throw new Exception(
                'Only shop owners can manage barbers.'
            );
        }

        $shops = $this->shopRepository->findByOwnerId($ownerId);

        if (empty($shops)) {
            return [];
        }

        $barbers = [];

        foreach ($shops as $shop) {
            $shopBarbers = $this->barberRepository->findByShopId(
                $shop->id
            );

            foreach ($shopBarbers as $barber) {
                $barberUser = $this->userRepository->findById(
                    $barber->userId
                );

                $barbers[] = [
                    'id' => $barber->id,
                    'user_id' => $barber->userId,
                    'shop_id' => $barber->shopId,

                    'first_name' => $barberUser?->firstName,
                    'last_name' => $barberUser?->lastName,
                    'email' => $barberUser?->email,
                    'phone' => $barberUser?->phone,
                    'profile_image' => $barberUser?->profileImage,

                    'experience' => $barber->experience,
                    'speciality' => $barber->speciality,

                    'approval_status' => $barber->approvalStatus,
                    'approved_at' => $barber->approvedAt,
                    'approved_by' => $barber->approvedBy,

                    'status' => $barber->status,
                ];
            }
        }

        return $barbers;
    }

    /**
     * Approve or reject a barber belonging to the
     * authenticated shop owner's shop.
     */
    public function updateBarberApproval(
        int $ownerId,
        int $barberId,
        string $approvalStatus
    ): void {
        if ($ownerId <= 0) {
            throw new Exception('Invalid authenticated user.');
        }

        if ($barberId <= 0) {
            throw new Exception('Invalid barber.');
        }

        if (!in_array(
            $approvalStatus,
            ['approved', 'rejected'],
            true
        )) {
            throw new Exception(
                'Invalid barber approval status.'
            );
        }

        $user = $this->userRepository->findById($ownerId);

        if (!$user) {
            throw new Exception('User not found.');
        }

        $roles = $this->userRepository->getRoles($ownerId);

        if (!in_array('SHOP_OWNER', $roles, true)) {
            throw new Exception(
                'Only shop owners can manage barbers.'
            );
        }

        $barber = $this->barberRepository->findById($barberId);

        if (!$barber) {
            throw new Exception('Barber not found.');
        }

        /*
         * Security check:
         * The barber must belong to a shop owned by
         * the authenticated shop owner.
         */
        $shop = $this->shopRepository->findById(
            $barber->shopId
        );

        if (!$shop) {
            throw new Exception(
                'The barber is not attached to a valid shop.'
            );
        }

        if ((int) $shop->ownerId !== $ownerId) {
            throw new Exception(
                'You are not authorized to manage this barber.'
            );
        }

        if ($barber->approvalStatus !== 'pending') {
            throw new Exception(
                'This barber application has already been processed.'
            );
        }

        $updated = $this->barberRepository->updateApprovalStatus(
            $barberId,
            $approvalStatus,
            $ownerId
        );

        if (!$updated) {
            throw new Exception(
                'Failed to update barber approval status.'
            );
        }
    }
}
