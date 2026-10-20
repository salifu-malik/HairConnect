<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Helpers\DatabaseManager;
use App\Repositories\BarberHomeServiceRepository;
use App\Repositories\BarberScheduleRepository;
use App\Repositories\AppointmentRepository;
use App\Repositories\BarberRepository;
use App\Repositories\ShopRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\CustomerBookingRestrictionRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\BookingService;
use App\Services\AppointmentService;

date_default_timezone_set('Africa/Accra');

$passed = 0;
$failed = 0;
$testAppointmentId = null;

function checkResult(string $description, bool $condition): void
{
    global $passed, $failed;

    if ($condition) {
        echo "[PASS] {$description}\n";
        $passed++;
    } else {
        echo "[FAIL] {$description}\n";
        $failed++;
    }
}

function findSlot(array $slots, string $time): ?array
{
    foreach ($slots as $slot) {
        if ($slot['time'] === $time) {
            return $slot;
        }
    }

    return null;
}

try {
    $db = DatabaseManager::getConnection('booking_db');

    $appointmentRepository = new AppointmentRepository();
    $barberRepository = new BarberRepository();
    $shopRepository = new ShopRepository();
    $serviceRepository = new ServiceRepository();
    $subscriptionRepository = new SubscriptionRepository();

    $bookingService = new BookingService(
        $shopRepository,
        $barberRepository,
        $serviceRepository,
        new BarberHomeServiceRepository(),
        $appointmentRepository,
        new BarberScheduleRepository(),
        $subscriptionRepository,
        new CustomerBookingRestrictionRepository()
    );

    $appointmentService = new AppointmentService(
        $appointmentRepository,
        $barberRepository,
        $shopRepository,
        new CustomerBookingRestrictionRepository()
    );

    /*
     * Use the existing approved shop-based barber and service.
     * Select the next Tuesday, when barber 1 has SHOP hours
     * from 09:00 to 17:00.
     */
    $date = new DateTimeImmutable('next Tuesday');
    $appointmentDate = $date->format('Y-m-d');

    $barberId = 1;
    $serviceId = 1;
    $customerId = 8;
    $serviceLocation = 'SHOP';
    $appointmentTime = '11:00';
    /*
     * Step 1: Check the slot before inserting our test appointment.
     */
    $initialSlots = $bookingService->getAvailableSlots(
        $barberId,
        $serviceId,
        $serviceLocation,
        $appointmentDate
    );

    $initialSlot = findSlot($initialSlots, $appointmentTime);

    checkResult(
        'The 11:00 slot is initially available',
        $initialSlot !== null
            && $initialSlot['status'] === 'available'
    );

    if (
        $initialSlot === null
        || $initialSlot['status'] !== 'available'
    ) {
        throw new RuntimeException(
            'The selected test slot is not initially available. '
            . 'Choose another future Tuesday slot before continuing.'
        );
    }

    /*
     * Step 2: Insert a dedicated test appointment directly into
     * the booking database.
     */
    $insert = $db->prepare("
        INSERT INTO appointments (
            customer_id,
            shop_id,
            barber_id,
            service_id,
            service_location,
            duration_minutes,
            appointment_date,
            appointment_time,
            status,
            booking_code
        ) VALUES (
            :customer_id,
            :shop_id,
            :barber_id,
            :service_id,
            :service_location,
            :duration_minutes,
            :appointment_date,
            :appointment_time,
            'pending',
            NULL
        )
    ");

    $insert->execute([
        'customer_id' => $customerId,
        'shop_id' => 1,
        'barber_id' => $barberId,
        'service_id' => $serviceId,
        'service_location' => $serviceLocation,
       'duration_minutes' => (
    strtotime($initialSlot['end_time'])
    - strtotime($initialSlot['time'])
) / 60,
        'appointment_date' => $appointmentDate,
        'appointment_time' => $appointmentTime,
    ]);

    $testAppointmentId = (int) $db->lastInsertId();

    checkResult(
        'A dedicated test appointment was created',
        $testAppointmentId > 0
    );

    /*
     * Step 3: Verify that the appointment blocks overlapping slots.
     */
    $bookedSlots = $bookingService->getAvailableSlots(
        $barberId,
        $serviceId,
        $serviceLocation,
        $appointmentDate
    );
    $slot1030 = findSlot($bookedSlots, '10:30');
    $slot1100 = findSlot($bookedSlots, '11:00');
    $slot1130 = findSlot($bookedSlots, '11:30');

    checkResult(
        'The overlapping 10:30 slot is booked',
        $slot1030 !== null && $slot1030['status'] === 'booked'
    );

    checkResult(
        'The 11:00 appointment slot is booked',
        $slot1100 !== null && $slot1100['status'] === 'booked'
    );

    checkResult(
        'The overlapping 11:30 slot is booked',
        $slot1130 !== null && $slot1130['status'] === 'booked'
    );

    /*
     * Step 4: Cancel the test appointment through AppointmentService.
     */
    $appointmentService->cancelAppointment(
        $testAppointmentId,
        $customerId
    );

    $statusQuery = $db->prepare(
        'SELECT status FROM appointments WHERE id = :id'
    );

    $statusQuery->execute(['id' => $testAppointmentId]);

    $cancelledStatus = $statusQuery->fetchColumn();

    checkResult(
        'The test appointment was cancelled',
        $cancelledStatus === 'cancelled'
    );

    /*
     * Step 5: Recalculate availability after cancellation.
     */
    $restoredSlots = $bookingService->getAvailableSlots(
        $barberId,
        $serviceId,
        $serviceLocation,
        $appointmentDate
    );

    $restored1030 = findSlot($restoredSlots, '10:30');
    $restored1100 = findSlot($restoredSlots, '11:00');
    $restored1130 = findSlot($restoredSlots, '11:30');

    checkResult(
        'The 10:30 slot becomes available after cancellation',
        $restored1030 !== null
        && $restored1030['status'] === 'available'
    );

    checkResult(
        'The 11:00 slot becomes available after cancellation',
        $restored1100 !== null
        && $restored1100['status'] === 'available'
    );

    checkResult(
        'The 11:30 slot becomes available after cancellation',
        $restored1130 !== null
        && $restored1130['status'] === 'available'
    );

} catch (Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    $failed++;
} finally {
    /*
     * Delete only the appointment created by this test.
     * Never delete pre-existing appointments.
     */
    if ($testAppointmentId !== null) {
        try {
            $db ??= DatabaseManager::getConnection('booking_db');

            $cleanup = $db->prepare("
                DELETE FROM appointments
                WHERE id = :id
                  AND customer_id = :customer_id
                  AND barber_id = :barber_id
                  AND service_id = :service_id
                  AND booking_code IS NULL
                  AND status = 'cancelled'
            ");

            $cleanup->execute([
                'id' => $testAppointmentId,
                'customer_id' => 8,
                'barber_id' => 1,
                'service_id' => 1,
            ]);

            if ($cleanup->rowCount() === 1) {
                echo "[CLEANUP] Removed test appointment "
                    . $testAppointmentId . "\n";
            } else {
                echo "[CLEANUP] Test appointment was not deleted. "
                    . "Inspect appointment ID "
                    . $testAppointmentId . " manually.\n";
            }
        } catch (Throwable $cleanupError) {
            echo "[CLEANUP ERROR] "
                . $cleanupError->getMessage() . "\n";
        }
    }
}

echo "\nResults: {$passed} passed, {$failed} failed\n";

exit($failed > 0 ? 1 : 0);
