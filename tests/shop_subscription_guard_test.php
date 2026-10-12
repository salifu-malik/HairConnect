<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Repositories\AppointmentRepository;
use App\Repositories\BarberHomeServiceRepository;
use App\Repositories\BarberRepository;
use App\Repositories\BarberScheduleRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\ShopRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\BookingService;

/**
 * Test double: simulate a shop owner without an eligible
 * subscription without modifying subscription_db.
 */
class InactiveSubscriptionRepository extends SubscriptionRepository
{
    public function hasActiveSubscription(int $userId): bool
    {
        return false;
    }
}

$bookingService = new BookingService(
    new ShopRepository(),
    new BarberRepository(),
    new ServiceRepository(),
    new BarberHomeServiceRepository(),
    new AppointmentRepository(),
    new BarberScheduleRepository(),
    new InactiveSubscriptionRepository()
);

$expectedMessage =
    'This shop owner does not have an active HairConnect subscription.';

$passed = 0;
$failed = 0;

function runGuardTest(
    string $name,
    callable $test,
    string $expectedMessage,
    int &$passed,
    int &$failed
): void {
    try {
        $test();

        echo "[FAIL] {$name}: No exception was thrown.\n";
        $failed++;
    } catch (Throwable $e) {
        if ($e->getMessage() === $expectedMessage) {
            echo "[PASS] {$name}\n";
            $passed++;
        } else {
            echo "[FAIL] {$name}: Unexpected exception: "
                . $e->getMessage() . "\n";
            $failed++;
        }
    }
}

/*
 * Test 1: Shop-based availability must be rejected.
 *
 * Barber 1 belongs to shop 1, whose owner is user 10.
 */
runGuardTest(
    'Shop availability blocked without an active owner subscription',
    function () use ($bookingService): void {
        $bookingService->getAvailableSlots(
            1,                          // barber_id
            1,                          // service_id
            'SHOP',
            date('Y-m-d', strtotime('+1 day'))
        );
    },
    $expectedMessage,
    $passed,
    $failed
);

/*
 * Test 2: Booking must be rejected before an appointment
 * can be created.
 */
runGuardTest(
    'Shop booking blocked without an active owner subscription',
    function () use ($bookingService): void {
        $bookingService->createAppointment(
            [
                'barber_id' => 1,
                'service_id' => 1,
                'service_location' => 'SHOP',
                'appointment_date' => date(
                    'Y-m-d',
                    strtotime('+1 day')
                ),
                'appointment_time' => '12:00',
            ],
            1 // Positive customer ID for validation
        );
    },
    $expectedMessage,
    $passed,
    $failed
);

echo "\nResults: {$passed} passed, {$failed} failed.\n";

exit($failed > 0 ? 1 : 0);
