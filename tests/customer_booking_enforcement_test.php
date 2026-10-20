<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Exceptions\BookingConflictException;
use App\Helpers\DatabaseManager;
use App\Repositories\AppointmentRepository;
use App\Repositories\BarberHomeServiceRepository;
use App\Repositories\BarberRepository;
use App\Repositories\BarberScheduleRepository;
use App\Repositories\CustomerBookingRestrictionRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\ShopRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\BookingService;

$db = DatabaseManager::getConnection('booking_db');

$timezone = new DateTimeZone('Africa/Accra');

$customerId = 8;
$barberId = 1;
$serviceId = 1;

$passed = 0;
$failed = 0;
$testRestrictionId = null;

$testRestrictionReason = 'TEST_booking_enforcement';

$expectedRestrictionMessage =
    'You cannot make a new booking because of a recent appointment cancellation.';

$bookingService = new BookingService(
    new ShopRepository(),
    new BarberRepository(),
    new ServiceRepository(),
    new BarberHomeServiceRepository(),
    new AppointmentRepository(),
    new BarberScheduleRepository(),
    new SubscriptionRepository(),
    new CustomerBookingRestrictionRepository()
);

/**
 * Run one test and record its result.
 */
function runBookingRestrictionTest(
    string $name,
    callable $test,
    int &$passed,
    int &$failed
): void {
    try {
        $test();

        echo "[PASS] {$name}\n";
        $passed++;
    } catch (Throwable $e) {
        echo "[FAIL] {$name}: {$e->getMessage()}\n";
        $failed++;
    }
}

/**
 * Count appointments without modifying any records.
 */
function countCustomerAppointments(
    PDO $db,
    int $customerId
): int {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM appointments
        WHERE customer_id = :customer_id
    ");

    $stmt->execute([
        'customer_id' => $customerId,
    ]);

    return (int) $stmt->fetchColumn();
}

/**
 * Verify that a booking attempt is rejected specifically
 * because of the cancellation restriction.
 *
 * Also verify that the appointment count remains unchanged.
 */
function assertBookingBlockedByRestriction(
    BookingService $bookingService,
    PDO $db,
    int $customerId,
    int $barberId,
    int $serviceId,
    string $location,
    string $appointmentDate,
    string $appointmentTime,
    string $expectedMessage
): void {
    $appointmentsBefore = countCustomerAppointments(
        $db,
        $customerId
    );

    try {
        $bookingService->createAppointment(
            [
                'barber_id' => $barberId,
                'service_id' => $serviceId,
                'service_location' => $location,
                'appointment_date' => $appointmentDate,
                'appointment_time' => $appointmentTime,
            ],
            $customerId
        );
    } catch (BookingConflictException $e) {
        if (strpos($e->getMessage(), $expectedMessage) !== 0) {
            throw new RuntimeException(
                'Booking was rejected for the wrong reason: '
                . $e->getMessage()
            );
        }

        $appointmentsAfter = countCustomerAppointments(
            $db,
            $customerId
        );

        if ($appointmentsAfter !== $appointmentsBefore) {
            throw new RuntimeException(
                'Rejected booking changed the customer appointment count.'
            );
        }

        return;
    }

    throw new RuntimeException(
        "Restricted customer was allowed to book {$location}."
    );
}

try {
    /*
  * Create a temporary restriction covering both the next
  * Tuesday SHOP booking and Saturday HOME booking.
  */
    $today = new DateTimeImmutable('today', $timezone);
    $restrictionEnd = (new DateTimeImmutable(
        'next Tuesday',
        $timezone
    ))->modify('+5 days');

    $stmt = $db->prepare("
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

    $stmt->execute([
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
   * Use valid operating days for each service location.
   */
    $shopAppointmentDate = (new DateTimeImmutable(
        'next Tuesday',
        $timezone
    ))->format('Y-m-d');

    $homeAppointmentDate = (new DateTimeImmutable(
        'next Saturday',
        $timezone
    ))->format('Y-m-d');

    /*
     * Test 1: SHOP booking is blocked, and no appointment
     * is created.
     */
    runBookingRestrictionTest(
        'Restricted customer cannot book SHOP; appointment count unchanged',
        function () use (
            $bookingService,
            $db,
            $customerId,
            $barberId,
            $serviceId,
            $shopAppointmentDate,
            $expectedRestrictionMessage
        ): void {
            assertBookingBlockedByRestriction(
                $bookingService,
                $db,
                $customerId,
                $barberId,
                $serviceId,
                'SHOP',
                $shopAppointmentDate,
                '12:00',
                $expectedRestrictionMessage
            );
        },
        $passed,
        $failed
    );

    /*
     * Test 2: HOME booking is blocked, and no appointment
     * is created.
     */
    runBookingRestrictionTest(
        'Restricted customer cannot book HOME; appointment count unchanged',
        function () use (
            $bookingService,
            $db,
            $customerId,
            $barberId,
            $serviceId,
            $homeAppointmentDate,
            $expectedRestrictionMessage
        ): void {
            assertBookingBlockedByRestriction(
                $bookingService,
                $db,
                $customerId,
                $barberId,
                $serviceId,
                'HOME',
                $homeAppointmentDate,
                '10:00',
                $expectedRestrictionMessage
            );
        },
        $passed,
        $failed
    );

} catch (Throwable $e) {
    echo "[ERROR] Test setup failed: {$e->getMessage()}\n";
    $failed++;

} finally {
    /*
     * Delete only the exact test-created restriction.
     * Do not delete customer appointments or historical restrictions.
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
                    . "{$testRestrictionId}\n";
            } else {
                echo "[WARNING] The test restriction was not removed.\n";
                $failed++;
            }

        } catch (Throwable $e) {
            echo "[WARNING] Restriction cleanup failed: "
                . $e->getMessage()
                . "\n";

            $failed++;
        }
    }
}

echo "\nResults: {$passed} passed, {$failed} failed.\n";

exit($failed > 0 ? 1 : 0);