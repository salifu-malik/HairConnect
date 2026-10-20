<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Helpers\DatabaseManager;
use App\Repositories\AppointmentRepository;
use App\Repositories\BarberRepository;
use App\Repositories\ShopRepository;
use App\Repositories\CustomerBookingRestrictionRepository;
use App\Repositories\NotificationRepository;
use App\Services\AppointmentService;

$db = DatabaseManager::getConnection('booking_db');

$notificationDb = DatabaseManager::getConnection('notification_db');

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
$restrictionIdBeforeTest = null;
$notificationIdsBeforeTest = [];


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
    $restrictionIdBeforeTest = (int) $db->query("
    SELECT COALESCE(MAX(id), 0)
    FROM customer_booking_restrictions
    WHERE customer_id = 8
")->fetchColumn();

    /*
 * Record the existing cancellation notifications for barber 1's
 * user account so we can identify notifications created by this test.
 */
    $barber = (new BarberRepository())->findById(1);

    if (!$barber || $barber->userId <= 0) {
        throw new RuntimeException(
            'Could not identify the test barber user account.'
        );
    }

    $notificationStmt = $notificationDb->prepare("
    SELECT COALESCE(MAX(id), 0)
    FROM notifications
    WHERE user_id = :user_id
      AND type = 'booking_cancelled'
");

    $notificationStmt->execute([
        'user_id' => $barber->userId,
    ]);

    $notificationIdsBeforeTest['barber'] = (int)
    $notificationStmt->fetchColumn();

    /*
 * Record the shop owner's existing cancellation notifications.
 */
    $shop = (new ShopRepository())->findById(1);

    if (!$shop || $shop->ownerId <= 0) {
        throw new RuntimeException(
            'Could not identify the test shop owner account.'
        );
    }

    $notificationStmt->execute([
        'user_id' => $shop->ownerId,
    ]);

    $notificationIdsBeforeTest['shop_owner'] = (int)
    $notificationStmt->fetchColumn();


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
     * Test 3: The barber receives a cancellation notification.
     */
    check(
        'Barber receives a cancellation notification',
        function () use (
            $notificationDb,
            $barber,
            $notificationIdsBeforeTest
        ): void {
            $stmt = $notificationDb->prepare("
            SELECT id, title, message, type, status
            FROM notifications
            WHERE user_id = :user_id
              AND type = 'booking_cancelled'
              AND id > :last_id
            ORDER BY id DESC
            LIMIT 1
        ");

            $stmt->execute([
                'user_id' => $barber->userId,
                'last_id' => $notificationIdsBeforeTest['barber'],
            ]);

            $notification = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$notification) {
                throw new RuntimeException(
                    'No cancellation notification was created for the barber.'
                );
            }

            if ($notification['title'] !== 'Appointment Cancelled') {
                throw new RuntimeException(
                    'The cancellation notification title is incorrect.'
                );
            }

            if ($notification['status'] !== 'unread') {
                throw new RuntimeException(
                    'The cancellation notification should initially be unread.'
                );
            }
        },
        $passed,
        $failed
    );


    /*
 * Test: The shop owner receives a cancellation notification.
 */
    check(
        'Shop owner receives a cancellation notification',
        function () use (
            $notificationDb,
            $shop,
            $notificationIdsBeforeTest
        ): void {
            $stmt = $notificationDb->prepare("
            SELECT id, title, message, type, status
            FROM notifications
            WHERE user_id = :user_id
              AND type = 'booking_cancelled'
              AND id > :last_id
            ORDER BY id DESC
            LIMIT 1
        ");

            $stmt->execute([
                'user_id' => $shop->ownerId,
                'last_id' => $notificationIdsBeforeTest['shop_owner'],
            ]);

            $notification = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$notification) {
                throw new RuntimeException(
                    'No cancellation notification was created for the shop owner.'
                );
            }

            if ($notification['title'] !== 'Shop Appointment Cancelled') {
                throw new RuntimeException(
                    'The shop-owner notification title is incorrect.'
                );
            }

            if ($notification['status'] !== 'unread') {
                throw new RuntimeException(
                    'The shop-owner notification should initially be unread.'
                );
            }
        },
        $passed,
        $failed
    );

    /*
     * Test 4: A different customer cannot cancel it.
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
     * Test 5: A second cancellation is rejected.
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
     * Delete only the dedicated test appointment.
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




    /*
     * Remove restrictions created by this test.
     */
    if ($restrictionIdBeforeTest !== null) {
        $cleanupRestrictions = $db->prepare("
            DELETE FROM customer_booking_restrictions
            WHERE customer_id = :customer_id
              AND id > :restriction_id
              AND reason = :reason
        ");

        $cleanupRestrictions->execute([
            'customer_id' => 8,
            'restriction_id' => $restrictionIdBeforeTest,
            'reason' => 'Appointment cancellation',
        ]);

        /*
  * Remove only cancellation notifications created by this test.
  */
        if (
            isset($notificationIdsBeforeTest['barber'])
            && isset($barber)
        ) {
            $cleanupNotifications = $notificationDb->prepare("
        DELETE FROM notifications
        WHERE user_id = :user_id
          AND type = 'booking_cancelled'
          AND id > :last_id
          AND title = 'Appointment Cancelled'
    ");

            $cleanupNotifications->execute([
                'user_id' => $barber->userId,
                'last_id' => $notificationIdsBeforeTest['barber'],
            ]);

            echo "[CLEANUP] Removed "
                . $cleanupNotifications->rowCount()
                . " barber test notification(s)\n";
        }

        if (
            isset($notificationIdsBeforeTest['shop_owner'])
            && isset($shop)
        ) {
            $cleanupShopNotifications = $notificationDb->prepare("
        DELETE FROM notifications
        WHERE user_id = :user_id
          AND type = 'booking_cancelled'
          AND id > :last_id
          AND title = 'Shop Appointment Cancelled'
    ");

            $cleanupShopNotifications->execute([
                'user_id' => $shop->ownerId,
                'last_id' => $notificationIdsBeforeTest['shop_owner'],
            ]);

            echo "[CLEANUP] Removed "
                . $cleanupShopNotifications->rowCount()
                . " shop-owner test notification(s)\n";
        }
    }



    /*
 * Remove only cancellation notifications created by this test
 * for the barber account.
 */
    if (
        isset($notificationIdsBeforeTest['barber'])
        && isset($barber)
    ) {
        $cleanupNotifications = $notificationDb->prepare("
        DELETE FROM notifications
        WHERE user_id = :user_id
          AND type = 'booking_cancelled'
          AND id > :last_id
          AND title = 'Appointment Cancelled'
    ");

        $cleanupNotifications->execute([
            'user_id' => $barber->userId,
            'last_id' => $notificationIdsBeforeTest['barber'],
        ]);

        echo "[CLEANUP] Removed "
            . $cleanupNotifications->rowCount()
            . " test cancellation notification(s)\n";
    }
}

echo "\nResults: {$passed} passed, {$failed} failed.\n";

exit($failed > 0 ? 1 : 0);
