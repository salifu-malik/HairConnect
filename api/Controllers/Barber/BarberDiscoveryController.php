<?php

namespace Api\Controllers\Barber;

use App\Repositories\BarberRepository;
use App\Repositories\ShopRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\UserRepository;
use Exception;

class BarberDiscoveryController
{
    private BarberRepository $barberRepository;
    private ShopRepository $shopRepository;
    private SubscriptionRepository $subscriptionRepository;
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->barberRepository = new BarberRepository();
        $this->shopRepository = new ShopRepository();
        $this->subscriptionRepository = new SubscriptionRepository();
        $this->userRepository = new UserRepository();
    }

    /**
     * GET /api/barbers
     *
     * Return only barbers who are eligible for public discovery.
     *
     * Independent barber:
     * - Must be approved and active.
     * - Must have an active subscription.
     *
     * Shop-based barber:
     * - Must be approved and active.
     * - Must belong to an approved and active shop.
     * - The shop owner must have an active subscription.
     */
    public function getBarbers(): void
    {
        try {
            $search = $_GET['search'] ?? null;

            if ($search !== null && trim($search) === '') {
                $search = null;
            }

            /*
             * Fetch approved and active barbers.
             *
             * Subscription eligibility is checked below in batches.
             * Redis caching is temporarily bypassed to prevent stale
             * subscription status from exposing ineligible barbers.
             */
            $barbers = $this->barberRepository
                ->findDiscoverableBarbers($search);

            /*
             * Collect the relevant subscription account IDs.
             *
             * Independent barber:
             *     barber.userId
             *
             * Shop-based barber:
             *     shop.ownerId
             */
            $subscriptionUserIds = [];
            $shopsByBarberId = [];

            foreach ($barbers as $barber) {
                if ($barber->shopId === null) {
                    $subscriptionUserIds[] = $barber->userId;
                    continue;
                }

                $shop = $this->shopRepository
                    ->findById($barber->shopId);

                /*
                 * Do not expose a shop-based barber if the associated
                 * shop cannot be found or is not eligible.
                 */
                if (
                    !$shop
                    || $shop->approvalStatus !== 'approved'
                    || $shop->status !== 'active'
                ) {
                    continue;
                }

                $shopsByBarberId[$barber->id] = $shop;

                $subscriptionUserIds[] = $shop->ownerId;
            }

            /*
             * Retrieve all active subscription holders in one query.
             */
            $activeSubscriptionUserIds =
                $this->subscriptionRepository
                    ->findUserIdsWithActiveSubscriptions(
                        $subscriptionUserIds
                    );

            $activeSubscriptionLookup = array_fill_keys(
                $activeSubscriptionUserIds,
                true
            );

            /*
             * Build the public response.
             */
            $data = [];

            foreach ($barbers as $barber) {
                /*
                 * Independent barber:
                 * The barber's own subscription must be active.
                 */
                if ($barber->shopId === null) {
                    if (
                        !isset(
                            $activeSubscriptionLookup[$barber->userId]
                        )
                    ) {
                        continue;
                    }

                    $shopName = null;
                    $location = null;
                } else {
                    /*
                     * Shop-based barber:
                     * The shop must exist, be approved and active,
                     * and its owner must have an active subscription.
                     */
                    $shop = $shopsByBarberId[$barber->id] ?? null;

                    if (!$shop) {
                        continue;
                    }

                    if (
                        !isset(
                            $activeSubscriptionLookup[$shop->ownerId]
                        )
                    ) {
                        continue;
                    }

                    $shopName = $shop->name;
                    $location = $shop->location;
                }

                /*
                 * Load the barber's public user profile.
                 */
                $user = $this->userRepository
                    ->findById($barber->userId);

                if (!$user) {
                    continue;
                }

                $data[] = [
                    'id' => $barber->id,
                    'name' => trim(
                        $user->firstName . ' ' . $user->lastName
                    ),
                    'profileImage' => $user->profileImage,
                    'shopId' => $barber->shopId,
                    'shopName' => $shopName,
                    'rating' => null,
                    'location' => $location,
                    'experience' => $barber->experience,
                    'speciality' => $barber->speciality,
                ];
            }

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $data,
            ]);

        } catch (Exception $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }
}