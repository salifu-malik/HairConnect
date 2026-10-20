<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Exceptions\BookingConflictException;
use App\Helpers\DatabaseManager;
use App\Models\Barber;
use App\Models\BarberHomeService;
use App\Repositories\AppointmentRepository;
use App\Repositories\BarberHomeServiceRepository;
use App\Repositories\BarberRepository;
use App\Repositories\BarberScheduleRepository;
use App\Repositories\CustomerBookingRestrictionRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\ShopRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\BookingService;

/**
 * Provide a controlled second-barber fixture without changing
 * the actual barber, schedule, or service records.
 */
class CrossBarberFixtureRepository extends BarberRepository
{
    public function findById(int $id): ?Barber
    {
        if ($id !== 2) {
            return null;
        }

        return new Barber([
            'id' => 2,
            'user_id' => 23,
            'shop_id' => null,
            'experience' => 2,
            'speciality' => 'Test barber',
            'approval_status' => 'approved',
            'status' => 'active',
        ]);
    }
}

class CrossBarberFixtureSubscriptionRepository extends SubscriptionRepository
{
    public function hasActiveSubscription(int $userId): bool
    {
        // Simulate an active subscription for the fixture only.
        return $userId === 23;
    }
}

class CrossBarberFixtureHomeServiceRepository extends BarberHomeServiceRepository
{
    public function findById(int $id): ?BarberHomeService
    {
        if ($id !== 2) {
            return null;
        }

        return new BarberHomeService([
            'id' => 2,
            'barber_id' => 2,
            'name' => 'Test HOME service',
            'duration_minutes' => 60,
            'price' => 80,
            'status' => 'active',
        ]);
    }
}

class CrossBarberFixtureScheduleRepository extends BarberScheduleRepository
{
    public function findByBarberAndDay(
        int $barberId,
        string $day,
        string $serviceLocation
    ): ?array {
        if ($barberId !== 2 || $serviceLocation !== 'HOME') {
            return null;
        }

        return [
            'id' => 999,
            'barber_id' => 2,
            'day' => $day,
            'service_location' => 'HOME',
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
        ];
    }
}

$db = DatabaseManager::getConnection('booking_db');
$timezone = new DateTimeZone('Africa/Accra');

$customerId = 8;
$barberId = 2;
$homeServiceId = 2;

$testRestrictionId = null;
$testRestrictionReason = 'TEST_cross_barber_booking_restriction';

$passed = 0;
$failed = 0;

try {
    /*
     * Create a restriction covering today and the next three
     * calendar days in Ghana time.
     */
    $today = new DateTimeImmutable('today', $timezone);
    $restrictionEnd = $today->modify('+3 days');

    $insert = $db->prepare("
        INSERT INTO customer_booking_restrictions (
            customer_id,
            restriction_start_date,
            restriction_end_date,
            reason
        ) VALUES (
            :customer_id,
            :start_date,
            :end_date,
            :reason
        )
    ");

    $insert->execute([
        'customer_id' => $customerId,
        'start_date' => $today->format('Y-m-d'),
        'end_date' => $restrictionEnd->format('Y-m-d'),
        'reason' => $testRestrictionReason,
    ]);

    $testRestrictionId = (int) $db->lastInsertId();

    if ($testRestrictionId <= 0) {
        throw new RuntimeException(
            'Could not identify the test-created restriction.'
        );
    }

    /*
     * Select the next Tuesday so the appointment date is valid
     * for the test fixture. The controlled fixture supplies its
     * own schedule and HOME service.
     */
    $appointmentDate = (new DateTimeImmutable(
        'next Tuesday',
        $timezone
    ))->format('Y-m-d');

    $bookingService = new BookingService(
        new ShopRepository(),
        new CrossBarberFixtureRepository(),
        new ServiceRepository(),
        new CrossBarberFixtureHomeServiceRepository(),
        new AppointmentRepository(),
        new CrossBarberFixtureScheduleRepository(),
        new CrossBarberFixtureSubscriptionRepository(),
        new CustomerBookingRestrictionRepository()
    );

    $countBeforeStmt = $db->prepare("
        SELECT COUNT(*)
        FROM appointments
        WHERE customer_id = :customer_id
    ");
    $countBeforeStmt->execute(['customer_id' => $customerId]);
    $countBefore = (int) $countBeforeStmt->fetchColumn();

    try {
        $bookingService->createAppointment(
            [
                'barber_id' => $barberId,
                'service_id' => $homeServiceId,
                'service_location' => 'HOME',
                'appointment_date' => $appointmentDate,
                'appointment_time' => '12:00',
            ],
            $customerId
        );

        throw new RuntimeException(
            'The restricted customer was allowed to book with another barber.'
        );
    } catch (BookingConflictException $e) {
        $expectedPrefix =
            'You cannot make a new booking because of a recent appointment cancellation.';

        if (strpos($e->getMessage(), $expectedPrefix) !== 0) {
            throw new RuntimeException(
                'Booking was rejected for the wrong reason: '
                . $e->getMessage()
            );
        }

        $countAfterStmt = $db->prepare("
            SELECT COUNT(*)
            FROM appointments
            WHERE customer_id = :customer_id
        ");
        $countAfterStmt->execute(['customer_id' => $customerId]);
        $countAfter = (int) $countAfterStmt->fetchColumn();

        if ($countAfter !== $countBefore) {
            throw new RuntimeException(
                'Rejected booking changed the customer appointment count.'
            );
        }

        echo "[PASS] Restricted customer cannot book with a different barber.\n";
        echo "[PASS] Rejected cross-barber booking creates no appointment.\n";
        $passed += 2;
    }

} catch (Throwable $e) {
    echo "[FAIL] {$e->getMessage()}\n";
    $failed++;

} finally {
    /*
     * Delete only the restriction created by this test.
     */
    if ($testRestrictionId !== null && $testRestrictionId > 0) {
        try {
            $cleanup = $db->prepare("
                DELETE FROM customer_booking_restrictions
                WHERE id = :id
                  AND customer_id = :customer_id
                  AND reason = :reason
            ");

            $cleanup->execute([
                'id' => $testRestrictionId,
                'customer_id' => $customerId,
                'reason' => $testRestrictionReason,
            ]);

            if ($cleanup->rowCount() === 1) {
                echo "[CLEANUP] Removed test restriction "
                    . $testRestrictionId . "\n";
            } else {
                echo "[WARNING] Test restriction was not removed.\n";
                $failed++;
            }

        } catch (Throwable $e) {
            echo "[WARNING] Restriction cleanup failed: "
                . $e->getMessage() . "\n";
            $failed++;
        }
    }
}

echo "\nResults: {$passed} passed, {$failed} failed.\n";

exit($failed > 0 ? 1 : 0);