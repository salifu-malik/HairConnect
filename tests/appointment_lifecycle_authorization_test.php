<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Helpers\DatabaseManager;
use App\Repositories\AppointmentRepository;
use App\Repositories\BarberRepository;
use App\Repositories\ShopRepository;
use App\Repositories\CustomerBookingRestrictionRepository;
use App\Services\AppointmentService;

$db = DatabaseManager::getConnection('booking_db');

$appointmentRepository = new AppointmentRepository();

$appointmentService = new AppointmentService(
    $appointmentRepository,
    new BarberRepository(),
    new ShopRepository(),
    new CustomerBookingRestrictionRepository()
);

$passed = 0;
$failed = 0;
$testAppointmentId = null;

function check(
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

try {
    /*
     * Create a dedicated test appointment.
     *
     * Status is set to in_progress so this test isolates
     * completion authorization from start-time validation.
     */
    $stmt = $db->prepare("
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
            8,
            1,
            1,
            1,
            'SHOP',
            60,
            :appointment_date,
            '15:00:00',
            'in_progress',
            NULL
        )
    ");

    $stmt->execute([
        'appointment_date' => date('Y-m-d'),
    ]);

    $testAppointmentId = (int) $db->lastInsertId();

    if ($testAppointmentId <= 0) {
        throw new RuntimeException(
            'Could not retrieve the test appointment ID.'
        );
    }

    /*
     * Test 1: Another barber cannot complete the appointment.
     * User ID 23 belongs to barber profile ID 2.
     */
    check(
        'Different barber cannot complete the appointment',
        function () use (
            $appointmentService,
            $testAppointmentId
        ): void {
            try {
                $appointmentService->completeAppointment(
                    $testAppointmentId,
                    23
                );
            } catch (Throwable $e) {
                if (
                    $e->getMessage()
                    === 'You are not authorized to complete this appointment.'
                ) {
                    return;
                }

                throw $e;
            }

            throw new RuntimeException(
                'Unauthorized completion was accepted.'
            );
        },
        $passed,
        $failed
    );

    /*
     * Test 2: Appointment remains in_progress.
     */
    check(
        'Unauthorized completion leaves status unchanged',
        function () use (
            $appointmentRepository,
            $testAppointmentId
        ): void {
            $appointment = $appointmentRepository->findById(
                $testAppointmentId
            );

            if (
                !$appointment
                || $appointment['status'] !== 'in_progress'
            ) {
                throw new RuntimeException(
                    'Appointment status changed unexpectedly.'
                );
            }
        },
        $passed,
        $failed
    );

    /*
     * Test 3: The assigned barber can complete the appointment.
     * User ID 9 belongs to barber profile ID 1.
     */
    check(
        'Assigned barber can complete an in-progress appointment',
        function () use (
            $appointmentService,
            $testAppointmentId
        ): void {
            $appointmentService->completeAppointment(
                $testAppointmentId,
                9
            );
        },
        $passed,
        $failed
    );

    /*
     * Test 4: Status is persisted as completed.
     */
    check(
        'Completed status is persisted',
        function () use (
            $appointmentRepository,
            $testAppointmentId
        ): void {
            $appointment = $appointmentRepository->findById(
                $testAppointmentId
            );

            if (
                !$appointment
                || $appointment['status'] !== 'completed'
            ) {
                throw new RuntimeException(
                    'Appointment status was not completed.'
                );
            }
        },
        $passed,
        $failed
    );
} catch (Throwable $e) {
    echo "[ERROR] Test setup failed: {$e->getMessage()}\n";
    $failed++;
} finally {
    /*
     * Delete only the dedicated test record.
     */
    if ($testAppointmentId !== null) {
        $cleanup = $db->prepare("
            DELETE FROM appointments
            WHERE id = :id
              AND customer_id = 8
              AND barber_id = 1
              AND service_id = 1
              AND status = 'completed'
              AND booking_code IS NULL
        ");

        $cleanup->execute([
            'id' => $testAppointmentId,
        ]);

        if ($cleanup->rowCount() !== 1) {
            echo "[WARNING] Test appointment cleanup requires inspection. "
                . "Appointment ID: {$testAppointmentId}\n";
            $failed++;
        } else {
            echo "[CLEANUP] Removed test appointment "
                . "{$testAppointmentId}\n";
        }
    }
}

echo "\nResults: {$passed} passed, {$failed} failed.\n";

exit($failed > 0 ? 1 : 0);