<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Helpers\DatabaseManager;
use App\Repositories\AppointmentRepository;
use App\Repositories\BarberRepository;
use App\Repositories\ShopRepository;
use App\Services\AppointmentService;

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
     * Use a dedicated test record.
     * Existing appointments are never selected for cancellation.
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
            :customer_id,
            :shop_id,
            :barber_id,
            :service_id,
            'SHOP',
            60,
            :appointment_date,
            '15:00:00',
            'pending',
            NULL
        )
    ");

    $stmt->execute([
        'customer_id' => 8,
        'shop_id' => 1,
        'barber_id' => 1,
        'service_id' => 1,
        'appointment_date' => date(
            'Y-m-d',
            strtotime('next Tuesday')
        ),
    ]);

    $testAppointmentId = (int) $db->lastInsertId();

    if ($testAppointmentId <= 0) {
        throw new RuntimeException(
            'Could not retrieve the test appointment ID.'
        );
    }

    /*
     * Test 1: The owning customer can cancel.
     */
    check(
        'Appointment owner can cancel a pending appointment',
        function () use (
            $appointmentService,
            $testAppointmentId
        ): void {
            $appointmentService->cancelAppointment(
                $testAppointmentId,
                8
            );
        },
        $passed,
        $failed
    );

    /*
     * Test 2: The database status is cancelled.
     */
    check(
        'Cancelled appointment is stored as cancelled',
        function () use (
            $appointmentRepository,
            $testAppointmentId
        ): void {
            $appointment = $appointmentRepository->findById(
                $testAppointmentId
            );

            if (
                !$appointment
                || $appointment['status'] !== 'cancelled'
            ) {
                throw new RuntimeException(
                    'Appointment status was not cancelled.'
                );
            }
        },
        $passed,
        $failed
    );

    /*
     * Test 3: A different customer cannot cancel it.
     */
    check(
        'Another customer cannot cancel the appointment',
        function () use (
            $appointmentService,
            $testAppointmentId
        ): void {
            try {
                $appointmentService->cancelAppointment(
                    $testAppointmentId,
                    999999
                );
            } catch (Throwable $e) {
                if (
                    $e->getMessage()
                    === 'You are not authorized to cancel this appointment.'
                ) {
                    return;
                }

                throw $e;
            }

            throw new RuntimeException(
                'Unauthorized cancellation was accepted.'
            );
        },
        $passed,
        $failed
    );

    /*
     * Test 4: A second cancellation is rejected.
     */
    check(
        'A cancelled appointment cannot be cancelled again',
        function () use (
            $appointmentService,
            $testAppointmentId
        ): void {
            try {
                $appointmentService->cancelAppointment(
                    $testAppointmentId,
                    8
                );
            } catch (Throwable $e) {
                if (
                    $e->getMessage()
                    === 'Only pending or confirmed appointments can be cancelled.'
                ) {
                    return;
                }

                throw $e;
            }

            throw new RuntimeException(
                'Second cancellation was accepted.'
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
     * Delete only the dedicated test record.
     */
    if ($testAppointmentId !== null) {
        $cleanup = $db->prepare("
            DELETE FROM appointments
            WHERE id = :id
              AND customer_id = 8
              AND barber_id = 1
              AND service_id = 1
              AND status = 'cancelled'
              AND booking_code IS NULL
        ");

        $cleanup->execute([
            'id' => $testAppointmentId,
        ]);

        if ($cleanup->rowCount() !== 1) {
            echo "[WARNING] Test appointment cleanup requires inspection. "
                . "Appointment ID: {$testAppointmentId}\n";
            $failed++;
        }
    }
}

echo "\nResults: {$passed} passed, {$failed} failed.\n";

exit($failed > 0 ? 1 : 0);
