<?php

namespace Api\Controllers\Barber;

use App\Helpers\RedisManager;
use App\Repositories\BarberRepository;
use App\Repositories\ShopRepository;
use App\Repositories\UserRepository;
use Exception;

class BarberDiscoveryController
{
    private BarberRepository $barberRepository;
    private ShopRepository $shopRepository;
    private UserRepository $userRepository;
    private RedisManager $redis;

    public function __construct()
    {
        $this->barberRepository = new BarberRepository();
        $this->shopRepository = new ShopRepository();
        $this->userRepository = new UserRepository();
        $this->redis = new RedisManager();
    }

    /**
     * GET /api/barbers
     *
     * Return barbers that customers can discover and book.
     */
    public function getBarbers(): void
    {
        try {
            $search = $_GET['search'] ?? null;

            /*
             * Only cache the default discovery request.
             *
             * Search requests will continue to query MariaDB directly
             * until we introduce search-specific cache keys.
             */
            $cacheKey = 'barbers:discoverable';

            if ($search === null || trim($search) === '') {
                $cachedData = $this->redis->get($cacheKey);

                if ($cachedData !== null) {
                    http_response_code(200);

                    echo json_encode([
                        'status' => 'success',
                        'data' => $cachedData
                    ]);

                    return;
                }
            }

            /*
             * Cache MISS:
             * Fetch the discoverable barbers from MariaDB.
             */
            $barbers = $this->barberRepository
                ->findDiscoverableBarbers($search);

            $data = [];

            foreach ($barbers as $barber) {
                $user = $this->userRepository
                    ->findById($barber->userId);

                if (!$user) {
                    continue;
                }

                $shopName = null;
                $location = null;

                if ($barber->shopId !== null) {
                    $shop = $this->shopRepository
                        ->findById($barber->shopId);

                    if ($shop) {
                        $shopName = $shop->name;
                        $location = $shop->location;
                    }
                }

                $data[] = [
                    'id' => $barber->id,
                    'name' => trim(
                        $user->firstName . ' ' . $user->lastName
                    ),
                    'profileImage' => $user->profileImage,
                    'shopName' => $shopName,
                    'rating' => null,
                    'location' => $location,
                    'experience' => $barber->experience,
                    'speciality' => $barber->speciality,
                ];
            }

            /*
             * Store only the default discovery result in Redis.
             *
             * TTL = 300 seconds = 5 minutes.
             */
            if ($search === null || trim($search) === '') {
                $this->redis->set(
                    $cacheKey,
                    $data,
                    300
                );
            }

            http_response_code(200);

            echo json_encode([
                'status' => 'success',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}