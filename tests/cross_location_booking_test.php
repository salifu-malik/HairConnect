<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Helpers\DatabaseManager;
use App\Repositories\AppointmentRepository;
use App\Repositories\BarberRepository;
use App\Repositories\ShopRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\CustomerBookingRestrictionRepository;
use App\Repositories\BarberHomeServiceRepository;
use App\Repositories\BarberScheduleRepository;
use App\Services\BookingService;

date_default_timezone_set('Africa/Accra');

$db = DatabaseManager::getConnection('booking_db');

$appointmentRepository = new AppointmentRepository();
$barberScheduleRepository = new BarberScheduleRepository();
$shopRepository = new ShopRepository();
$barberRepository = new BarberRepository();
$serviceRepository = new ServiceRepository();
$subscriptionRepository = new SubscriptionRepository();

$bookingService = new BookingService(
    $shopRepository,
    $barberRepository,
    $serviceRepository,
    new BarberHomeServiceRepository(),
    $appointmentRepository,
    $barberScheduleRepository,
    $subscriptionRepository,
    new CustomerBookingRestrictionRepository()
);

$barberId = 1;
$shopServiceId = 1;
$homeServiceId = 1;

$shopCustomerId = 6;
$homeCustomerId = 11;

$date = (new DateTimeImmutable(
    'next Tuesday',
    new DateTimeZone('Africa/Accra')
))->format('Y-m-d');

$createdIds = [];
$passed = 0;
$failed = 0;

$originalHomeSchedule = null;
$homeScheduleChanged = false;
$temporaryHomeScheduleId = null;

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

function createTestBooking(
    BookingService $service,
    PDO $db,
    array $data,
    int $customerId,
    array &$createdIds
): array {
    $beforeId = (int) $db->query(
        "SELECT COALESCE(MAX(id), 0) FROM appointments"
    )->fetchColumn();

    $result = $service->createAppointment($data, $customerId);

    $stmt = $db->prepare("
        SELECT id, status
        FROM appointments
        WHERE customer_id = :customer_id
          AND barber_id = :barber_id
          AND appointment_date = :date
          AND appointment_time = :time
          AND service_location = :location
          AND id > :before_id
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([
        ':customer_id' => $customerId,
        ':barber_id' => (int) $data['barber_id'],
        ':date' => $data['appointment_date'],
        ':time' => $data['appointment_time'] . ':00',
        ':location' => $data['service_location'],
        ':before_id' => $beforeId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException(
            'The created test appointment could not be identified.'
        );
    }

    $createdIds[] = [
        'id' => (int) $row['id'],
        'customer_id' => $customerId,
        'barber_id' => (int) $data['barber_id'],
        'date' => $data['appointment_date'],
        'time' => $data['appointment_time'] . ':00',
        'location' => $data['service_location'],
    ];

    return [
        'id' => (int) $row['id'],
        'status' => $row['status'],
        'result' => $result,
    ];
}

try {
    /*
  * Save the original Tuesday HOME schedule, if one exists.
  */
    $stmt = $db->prepare("
    SELECT id, barber_id, day, service_location,
           start_time, end_time
    FROM barber_schedule
    WHERE barber_id = ?
      AND day = 'Tuesday'
      AND service_location = 'HOME'
    LIMIT 1
");

    $stmt->execute([$barberId]);

    $originalHomeSchedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($originalHomeSchedule) {
        // Temporarily configure the existing schedule.
        $stmt = $db->prepare("
        UPDATE barber_schedule
        SET start_time = '09:00:00',
            end_time = '21:00:00'
        WHERE id = ?
    ");

        $stmt->execute([(int) $originalHomeSchedule['id']]);

        $homeScheduleChanged = true;
    } else {
        // Create a temporary schedule for this test only.
        $stmt = $db->prepare("
        INSERT INTO barber_schedule (
            barber_id,
            day,
            service_location,
            start_time,
            end_time
        ) VALUES (
            :barber_id,
            'Tuesday',
            'HOME',
            '09:00:00',
            '21:00:00'
        )
    ");

        $stmt->execute([
            ':barber_id' => $barberId,
        ]);

        $temporaryHomeScheduleId = (int) $db->lastInsertId();

        if ($temporaryHomeScheduleId <= 0) {
            throw new RuntimeException(
                'Could not create the temporary Tuesday HOME schedule.'
            );
        }

        echo "[SETUP] Created temporary Tuesday HOME schedule "
            . $temporaryHomeScheduleId . "\n";
    }

    /*
     * Test 1: SHOP appointment from 10:00 to 11:00.
     */
    $shopBooking = createTestBooking(
        $bookingService,
        $db,
        [
            'barber_id' => $barberId,
            'service_id' => $shopServiceId,
            'service_location' => 'SHOP',
            'appointment_date' => $date,
            'appointment_time' => '10:00',
        ],
        $shopCustomerId,
        $createdIds
    );

    checkResult(
        'SHOP booking at 10:00 is created',
        $shopBooking['id'] > 0
    );

    checkResult(
        'SHOP booking is automatically confirmed',
        $shopBooking['status'] === 'confirmed'
    );

    /*
  * Test 2: HOME appointment from 10:30 to 11:30.
  * It must be rejected because the SHOP appointment occupies
  * 10:00 to 11:00.
  */
    $overlapRejected = false;

    try {
        createTestBooking(
            $bookingService,
            $db,
            [
                'barber_id' => $barberId,
                'service_id' => $homeServiceId,
                'service_location' => 'HOME',
                'appointment_date' => $date,
                'appointment_time' => '10:30',
            ],
            $homeCustomerId,
            $createdIds
        );
    } catch (\App\Exceptions\BookingConflictException $e) {
        $overlapRejected = true;

        echo "[INFO] Booking conflict detected: "
            . $e->getMessage() . "\n";
    } catch (Throwable $e) {
        echo "[ERROR] Unexpected rejection reason: "
            . $e->getMessage() . "\n";
    }

    checkResult(
        'Overlapping HOME booking is rejected by the booking conflict rule',
        $overlapRejected
    );

    /*
     * Test 3: HOME appointment at 11:00.
     * It starts exactly when the SHOP appointment ends.
     * Adjacent appointments should be allowed.
     */
    $adjacentBooking = createTestBooking(
        $bookingService,
        $db,
        [
            'barber_id' => $barberId,
            'service_id' => $homeServiceId,
            'service_location' => 'HOME',
            'appointment_date' => $date,
            'appointment_time' => '11:00',
        ],
        $homeCustomerId,
        $createdIds
    );

    checkResult(
        'Adjacent HOME booking at 11:00 is accepted',
        $adjacentBooking['id'] > 0
    );

    checkResult(
        'Adjacent HOME booking is automatically confirmed',
        $adjacentBooking['status'] === 'confirmed'
    );

} catch (Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $failed++;
} finally {
    /*
     * Remove only appointments created and tracked by this test.
     */
    foreach (array_reverse($createdIds) as $booking) {
        $stmt = $db->prepare("
            DELETE FROM appointments
            WHERE id = :id
              AND customer_id = :customer_id
              AND barber_id = :barber_id
              AND appointment_date = :date
              AND appointment_time = :time
              AND service_location = :location
        ");

        $stmt->execute([
            ':id' => $booking['id'],
            ':customer_id' => $booking['customer_id'],
            ':barber_id' => $booking['barber_id'],
            ':date' => $booking['date'],
            ':time' => $booking['time'],
            ':location' => $booking['location'],
        ]);

        if ($stmt->rowCount() === 1) {
            echo "[CLEANUP] Removed test appointment "
                . $booking['id'] . "\n";
        }
    }

    /*
  * Restore the original HOME schedule if it was modified.
  */
    if ($homeScheduleChanged && $originalHomeSchedule !== null) {
        $stmt = $db->prepare("
        UPDATE barber_schedule
        SET start_time = :start_time,
            end_time = :end_time
        WHERE id = :id
    ");

        $stmt->execute([
            ':start_time' => $originalHomeSchedule['start_time'],
            ':end_time' => $originalHomeSchedule['end_time'],
            ':id' => $originalHomeSchedule['id'],
        ]);

        echo "[CLEANUP] Original HOME schedule restored\n";
    }

    /*
     * Remove the temporary HOME schedule created by this test.
     */
    if ($temporaryHomeScheduleId !== null) {
        $stmt = $db->prepare("
        DELETE FROM barber_schedule
        WHERE id = :id
          AND barber_id = :barber_id
          AND day = 'Tuesday'
          AND service_location = 'HOME'
    ");

        $stmt->execute([
            ':id' => $temporaryHomeScheduleId,
            ':barber_id' => $barberId,
        ]);

        if ($stmt->rowCount() === 1) {
            echo "[CLEANUP] Removed temporary HOME schedule "
                . $temporaryHomeScheduleId . "\n";
        } else {
            echo "[ERROR] Could not verify removal of temporary HOME schedule\n";
            $failed++;
        }
    }

    echo "\nResults: {$passed} passed, {$failed} failed\n";
}