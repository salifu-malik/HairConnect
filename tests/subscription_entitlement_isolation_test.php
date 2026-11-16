<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Models\Barber;
use App\Models\BarberHomeService;
use App\Models\Service;
use App\Models\Shop;
use App\Repositories\AppointmentRepository;
use App\Repositories\BarberHomeServiceRepository;
use App\Repositories\BarberRepository;
use App\Repositories\BarberScheduleRepository;
use App\Repositories\BarberShopMembershipRepository;
use App\Repositories\CustomerBookingRestrictionRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\ShopRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\BookingService;

/*
|--------------------------------------------------------------------------
| Controlled barber fixtures
|--------------------------------------------------------------------------
*/

class EntitlementFixtureBarberRepository extends BarberRepository
{
    public function findById(int $id): ?Barber
    {
        $fixtures = [
            1 => [
                'id' => 1,
                'user_id' => 101,
                'shop_id' => 1,
                'approval_status' => 'approved',
                'status' => 'active',
            ],
            2 => [
                'id' => 2,
                'user_id' => 102,
                'shop_id' => null,
                'approval_status' => 'approved',
                'status' => 'active',
            ],
        ];

        return isset($fixtures[$id])
            ? new Barber($fixtures[$id])
            : null;
    }
}

/*
|--------------------------------------------------------------------------
| Controlled shop fixture
|--------------------------------------------------------------------------
*/

class EntitlementFixtureShopRepository extends ShopRepository
{
    public function findById(int $id): ?Shop
    {
        if ($id !== 1) {
            return null;
        }

        return new Shop([
            'id' => 1,
            'owner_id' => 201,
            'name' => 'Test Shop',
            'location' => 'Accra',
            'approval_status' => 'approved',
            'status' => 'active',
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| Controlled subscription entitlements
|--------------------------------------------------------------------------
*/

class EntitlementFixtureSubscriptionRepository extends SubscriptionRepository
{
    public function hasActiveEntitlement(
        string $beneficiaryType,
        int $beneficiaryId,
        string $scope
    ): bool {
        /*
         * Barber 1 and barber 2 have valid HOME entitlements.
         * Shop 1 has no valid SHOP entitlement.
         */
        return (
            $beneficiaryType === 'BARBER'
            && in_array($beneficiaryId, [1, 2], true)
            && $scope === 'HOME'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Controlled membership fixture
|--------------------------------------------------------------------------
*/

class EntitlementFixtureMembershipRepository extends BarberShopMembershipRepository
{
    public function findActiveByBarberId(int $barberId): ?array
    {
        /*
         * No active membership is deliberately returned.
         * HOME authorization must not depend on membership.
         */
        return null;
    }
}


class ActiveEntitlementFixtureMembershipRepository extends BarberShopMembershipRepository
{
    public function findActiveByBarberId(int $barberId): ?array
    {
        if ($barberId !== 1) {
            return null;
        }

        return [
            'id' => 999,
            'barber_id' => 1,
            'shop_id' => 1,
            'status' => 'active',
        ];
    }
}


/*
|--------------------------------------------------------------------------
| Controlled service fixtures
|--------------------------------------------------------------------------
*/

class EntitlementFixtureServiceRepository extends ServiceRepository
{
    public function findById(int $id): ?Service
    {
        if ($id !== 10) {
            return null;
        }

        return new Service([
            'id' => 10,
            'shop_id' => 1,
            'barber_id' => null,
            'name' => 'Test SHOP service',
            'duration_minutes' => 60,
            'price' => 100,
            'status' => 'active',
        ]);
    }
}

class EntitlementFixtureHomeServiceRepository extends BarberHomeServiceRepository
{
    public function findById(int $id): ?BarberHomeService
    {
        $fixtures = [
            20 => [
                'id' => 20,
                'barber_id' => 1,
                'name' => 'Test HOME service - Shop barber',
                'duration_minutes' => 60,
                'price' => 80,
                'status' => 'active',
            ],
            21 => [
                'id' => 21,
                'barber_id' => 2,
                'name' => 'Test HOME service - Independent barber',
                'duration_minutes' => 60,
                'price' => 80,
                'status' => 'active',
            ],
        ];

        return isset($fixtures[$id])
            ? new BarberHomeService($fixtures[$id])
            : null;
    }
}

/*
|--------------------------------------------------------------------------
| Controlled schedule and appointment fixtures
|--------------------------------------------------------------------------
*/

class EntitlementFixtureScheduleRepository extends BarberScheduleRepository
{
    public function findByBarberAndDay(
        int $barberId,
        string $day,
        string $serviceLocation
    ): ?array {
        return null;
    }
}

class EntitlementFixtureAppointmentRepository extends AppointmentRepository
{
    public function findByBarberAndDate(
        int $barberId,
        string $appointmentDate
    ): array {
        return [];
    }
}

/*
|--------------------------------------------------------------------------
| Build BookingService
|--------------------------------------------------------------------------
*/

$bookingService = new BookingService(
    new EntitlementFixtureShopRepository(),
    new EntitlementFixtureBarberRepository(),
    new EntitlementFixtureServiceRepository(),
    new EntitlementFixtureHomeServiceRepository(),
    new EntitlementFixtureAppointmentRepository(),
    new EntitlementFixtureScheduleRepository(),
    new EntitlementFixtureSubscriptionRepository(),
    new CustomerBookingRestrictionRepository(),
    new EntitlementFixtureMembershipRepository()
);


$shopBookingService = new BookingService(
    new ShopRepository(),
    new EntitlementFixtureBarberRepository(),
    new EntitlementFixtureServiceRepository(),
    new EntitlementFixtureHomeServiceRepository(),
    new EntitlementFixtureAppointmentRepository(),
    new EntitlementFixtureScheduleRepository(),
    new EntitlementFixtureSubscriptionRepository(),
    new CustomerBookingRestrictionRepository(),
    new ActiveEntitlementFixtureMembershipRepository()
);

/*
|--------------------------------------------------------------------------
| Test runner
|--------------------------------------------------------------------------
*/

$passed = 0;
$failed = 0;

function runEntitlementTest(
    string $description,
    callable $test,
    bool $expectedToSucceed,
    int &$passed,
    int &$failed
): void {
    try {
        $test();

        if ($expectedToSucceed) {
            echo "[PASS] {$description}\n";
            $passed++;
        } else {
            echo "[FAIL] {$description}: Expected rejection, but request succeeded.\n";
            $failed++;
        }
    } catch (Throwable $e) {
        if (!$expectedToSucceed && $e->getMessage() ===
            'This shop owner does not have an active HairConnect subscription.') {
            echo "[PASS] {$description}\n";
            $passed++;
        } elseif (!$expectedToSucceed &&
            $e->getMessage() ===
            'Independent barbers cannot provide shop services.') {
            echo "[PASS] {$description}\n";
            $passed++;
        } else {
            echo "[FAIL] {$description}: {$e->getMessage()}\n";
            $failed++;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Use a future date
|--------------------------------------------------------------------------
*/

$date = (new DateTimeImmutable(
    'next Tuesday',
    new DateTimeZone('Africa/Accra')
))->format('Y-m-d');

/*
|--------------------------------------------------------------------------
| Test 1: Shop-affiliated barber HOME entitlement
|--------------------------------------------------------------------------
*/

runEntitlementTest(
    'Shop-affiliated barber can use HOME entitlement without active shop membership',
    function () use ($bookingService, $date): void {
        $bookingService->getAvailableSlots(
            1,
            20,
            'HOME',
            $date
        );
    },
    true,
    $passed,
    $failed
);

/*
|--------------------------------------------------------------------------
| Test 2: SHOP entitlement is independent
|--------------------------------------------------------------------------
*/

runEntitlementTest(
    'SHOP service is rejected when the shop lacks a valid SHOP entitlement',
    function () use ($shopBookingService, $date): void {
        $shopBookingService->getAvailableSlots(
            1,
            10,
            'SHOP',
            $date
        );
    },
    false,
    $passed,
    $failed
);

/*
|--------------------------------------------------------------------------
| Test 3: Independent barber HOME entitlement
|--------------------------------------------------------------------------
*/

runEntitlementTest(
    'Independent barber can use a valid HOME entitlement',
    function () use ($bookingService, $date): void {
        $bookingService->getAvailableSlots(
            2,
            21,
            'HOME',
            $date
        );
    },
    true,
    $passed,
    $failed
);

/*
|--------------------------------------------------------------------------
| Test 4: Independent barber cannot provide SHOP services
|--------------------------------------------------------------------------
*/

runEntitlementTest(
    'Independent barber cannot provide SHOP services',
    function () use ($bookingService, $date): void {
        $bookingService->getAvailableSlots(
            2,
            10,
            'SHOP',
            $date
        );
    },
    false,
    $passed,
    $failed
);

/*
|--------------------------------------------------------------------------
| Results
|--------------------------------------------------------------------------
*/

echo "\nResults: {$passed} passed, {$failed} failed.\n";

exit($failed > 0 ? 1 : 0);
