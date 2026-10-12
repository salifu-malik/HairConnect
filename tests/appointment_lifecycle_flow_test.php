<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Helpers\DatabaseManager;
use App\Repositories\AppointmentRepository;
use App\Repositories\BarberRepository;
use App\Repositories\ShopRepository;
use App\Services\AppointmentService;

date_default_timezone_set('Africa/Accra');

$db = DatabaseManager::getConnection('booking_db');

$appointmentRepository = new AppointmentRepository();

$appointmentService = new AppointmentService(
    $appointmentRepository,
    new BarberRepository(),
    new ShopRepository()
);

$passed = 0;
$failed = 0;
$testAppointmentId = null;
$bookingCode = null;

$customerId = 6;
$shopId = 1;
$shopOwnerUserId = 10;
$assignedBarberUserId = 9;
$otherBarberUserId = 23;
$barberId = 1;
$serviceId = 1;

$appointmentDate = (new DateTimeImmutable(
    'today',
    new DateTimeZone('Africa/Accra')
))->format('Y-m-d');

// This time is deliberately in the past so the start-time
// validation does not depend on when the test is executed.
$appointmentTime = '00:01:00';

function lifecycleCheck(
    string $description,
    bool $condition
): void {
    global $passed, $failed;

    if ($condition) {
        echo "[PASS] {$description}\n";
        $passed++;
    } else {
        echo "[FAIL] {$description}\n";
        $failed++;
    }
}

function lifecycleExpectException(
    string $description,
    callable $operation,
    string $expectedMessage
): void {
    try {
        $operation();

        lifecycleCheck($description, false);
    } catch (Throwable $e) {
        lifecycleCheck(
            $description,
            $e->getMessage() === $expectedMessage
        );

        if ($e->getMessage() !== $expectedMessage) {
            echo "       Actual error: {$e->getMessage()}\n";
        }
    }
}

function lifecycleStatus(int $appointmentId): ?string
{
    global $db;

    $stmt = $db->prepare(
        'SELECT status FROM appointments WHERE id = :id'
    );

    $stmt->execute(['id' => $appointmentId]);

    $status = $stmt->fetchColumn();

    return $status === false ? null : (string) $status;
}

try {
    /*
     * Generate an unused five-digit booking code.
     */
    $codeQuery = $db->prepare(
        'SELECT id FROM appointments WHERE booking_code = :code LIMIT 1'
    );

    for ($attempt = 0; $attempt < 20; $attempt++) {
        $candidate = (string) random_int(10000, 99999);

        $codeQuery->execute(['code' => $candidate]);

        if ($codeQuery->fetchColumn() === false) {
            $bookingCode = $candidate;
            break;
        }
    }

    if ($bookingCode === null) {
        throw new RuntimeException(
            'Could not generate an unused booking code.'
        );
    }

    /*
     * Create a dedicated SHOP appointment.
     *
     * This direct insert intentionally bypasses booking validation.
     * The lifecycle operations themselves use AppointmentService.
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
            'SHOP',
            60,
            :appointment_date,
            :appointment_time,
            'confirmed',
            :booking_code
        )
    ");

    $insert->execute([
        'customer_id' => $customerId,
        'shop_id' => $shopId,
        'barber_id' => $barberId,
        'service_id' => $serviceId,
        'appointment_date' => $appointmentDate,
        'appointment_time' => $appointmentTime,
        'booking_code' => $bookingCode,
    ]);

    $testAppointmentId = (int) $db->lastInsertId();

    if ($testAppointmentId <= 0) {
        throw new RuntimeException(
            'Could not create the lifecycle test appointment.'
        );
    }

    echo "[INFO] Temporary appointment ID: {$testAppointmentId}\n";

    /*
     * Test 1: Starting before check-in must fail.
     */
    lifecycleExpectException(
        'SHOP appointment cannot start before check-in',
        fn() => $appointmentService->startAppointment(
            $testAppointmentId,
            $assignedBarberUserId
        ),
        'Shop appointments must be checked in before they can be started.'
    );

    lifecycleCheck(
        'Rejected start leaves appointment confirmed',
        lifecycleStatus($testAppointmentId) === 'confirmed'
    );

    /*
     * Test 2: An incorrect booking code must be rejected.
     */
    $wrongCode = $bookingCode === '00000' ? '00001' : '00000';

    lifecycleExpectException(
        'Incorrect booking code is rejected',
        fn() => $appointmentService->checkInAppointment(
            $testAppointmentId,
            $shopOwnerUserId,
            $wrongCode
        ),
        'Invalid booking code.'
    );

    lifecycleCheck(
        'Rejected check-in leaves appointment confirmed',
        lifecycleStatus($testAppointmentId) === 'confirmed'
    );

    /*
     * Test 3: Only the assigned barber can start the appointment.
     */
    $appointmentService->checkInAppointment(
        $testAppointmentId,
        $shopOwnerUserId,
        $bookingCode
    );

    lifecycleCheck(
        'Correct shop owner checks in the customer',
        lifecycleStatus($testAppointmentId) === 'checked_in'
    );

    $stmt = $db->prepare("
        SELECT checked_in_at, checked_in_by
        FROM appointments
        WHERE id = :id
    ");

    $stmt->execute(['id' => $testAppointmentId]);

    $checkInData = $stmt->fetch(PDO::FETCH_ASSOC);

    lifecycleCheck(
        'Check-in timestamp and shop owner are persisted',
        is_array($checkInData)
        && !empty($checkInData['checked_in_at'])
        && (int) $checkInData['checked_in_by'] === $shopOwnerUserId
    );

    lifecycleExpectException(
        'Different barber cannot start the appointment',
        fn() => $appointmentService->startAppointment(
            $testAppointmentId,
            $otherBarberUserId
        ),
        'You are not authorized to start this appointment.'
    );

    lifecycleCheck(
        'Unauthorized start leaves appointment checked in',
        lifecycleStatus($testAppointmentId) === 'checked_in'
    );

    /*
     * Test 4: The assigned barber starts the appointment.
     */
    $appointmentService->startAppointment(
        $testAppointmentId,
        $assignedBarberUserId
    );

    lifecycleCheck(
        'Assigned barber starts the appointment',
        lifecycleStatus($testAppointmentId) === 'in_progress'
    );

    /*
     * Test 5: Repeating the start operation must fail.
     */
    lifecycleExpectException(
        'Appointment cannot be started twice',
        fn() => $appointmentService->startAppointment(
            $testAppointmentId,
            $assignedBarberUserId
        ),
        'Appointment has already been started.'
    );

    lifecycleCheck(
        'Repeated start leaves appointment in progress',
        lifecycleStatus($testAppointmentId) === 'in_progress'
    );

    /*
     * Test 6: Only the assigned barber can complete it.
     */
    lifecycleExpectException(
        'Different barber cannot complete the appointment',
        fn() => $appointmentService->completeAppointment(
            $testAppointmentId,
            $otherBarberUserId
        ),
        'You are not authorized to complete this appointment.'
    );

    lifecycleCheck(
        'Unauthorized completion leaves appointment in progress',
        lifecycleStatus($testAppointmentId) === 'in_progress'
    );

    /*
     * Test 7: The assigned barber completes the appointment.
     */
    $appointmentService->completeAppointment(
        $testAppointmentId,
        $assignedBarberUserId
    );

    lifecycleCheck(
        'Assigned barber completes the appointment',
        lifecycleStatus($testAppointmentId) === 'completed'
    );

    $stmt = $db->prepare(
        'SELECT completed_at FROM appointments WHERE id = :id'
    );

    $stmt->execute(['id' => $testAppointmentId]);

    $completedAt = $stmt->fetchColumn();

    lifecycleCheck(
        'Completion timestamp is persisted',
        $completedAt !== false && !empty($completedAt)
    );

    /*
     * Test 8: Repeating completion must fail.
     */
    lifecycleExpectException(
        'Completed appointment cannot be completed again',
        fn() => $appointmentService->completeAppointment(
            $testAppointmentId,
            $assignedBarberUserId
        ),
        'Only appointments in progress can be completed.'
    );

    lifecycleCheck(
        'Repeated completion leaves appointment completed',
        lifecycleStatus($testAppointmentId) === 'completed'
    );

} catch (Throwable $e) {
    echo "[ERROR] Lifecycle test setup/execution failed: "
        . $e->getMessage() . "\n";

    $failed++;

} finally {
    /*
     * Delete only the exact temporary fixture created by this test.
     * Match its identifying fields, but do not require a particular
     * status because the test might fail partway through the lifecycle.
     */
    if ($testAppointmentId !== null && $bookingCode !== null) {
        try {
            $cleanup = $db->prepare("
                DELETE FROM appointments
                WHERE id = :id
                  AND customer_id = :customer_id
                  AND shop_id = :shop_id
                  AND barber_id = :barber_id
                  AND service_id = :service_id
                  AND service_location = 'SHOP'
                  AND appointment_date = :appointment_date
                  AND appointment_time = :appointment_time
                  AND booking_code = :booking_code
            ");

            $cleanup->execute([
                'id' => $testAppointmentId,
                'customer_id' => $customerId,
                'shop_id' => $shopId,
                'barber_id' => $barberId,
                'service_id' => $serviceId,
                'appointment_date' => $appointmentDate,
                'appointment_time' => $appointmentTime,
                'booking_code' => $bookingCode,
            ]);

            if ($cleanup->rowCount() === 1) {
                echo "[CLEANUP] Removed temporary appointment "
                    . "{$testAppointmentId}\n";
            } else {
                echo "[WARNING] Could not verify cleanup for appointment "
                    . "{$testAppointmentId}. Inspect it manually.\n";

                $failed++;
            }
        } catch (Throwable $cleanupError) {
            echo "[WARNING] Cleanup failed: "
                . $cleanupError->getMessage() . "\n";

            $failed++;
        }
    }
}

echo "\nResults: {$passed} passed, {$failed} failed.\n";

if ($failed > 0) {
    exit(1);
}
