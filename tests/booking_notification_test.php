<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Helpers\DatabaseManager;
use App\Repositories\AppointmentRepository;
use App\Repositories\BarberHomeServiceRepository;
use App\Repositories\BarberRepository;
use App\Repositories\BarberScheduleRepository;
use App\Repositories\CustomerBookingRestrictionRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\ShopRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\BookingService;

date_default_timezone_set('Africa/Accra');

$bookingDb = DatabaseManager::getConnection('booking_db');
$notificationDb = DatabaseManager::getConnection('notification_db');

$barberId = 1;
$shopId = 1;
$shopServiceId = 1;
$homeServiceId = 1;
$shopCustomerId = 6;
$homeCustomerId = 11;

$barber = (new BarberRepository())->findById($barberId);
$shop = (new ShopRepository())->findById($shopId);

$passed = 0;
$failed = 0;

$createdAppointments = [];
$createdNotifications = [];
$originalHomeSchedule = null;
$homeScheduleChanged = false;
$temporaryHomeScheduleId = null;
$barberUserId = 0;
$shopOwnerId = 0;

function bookingNotificationCheck(string $description, bool $condition): void
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

/**
 * Return a lookup set of existing booking-created notification IDs
 * for the supplied users.
 *
 * @return array<int, bool>
 */
function snapshotBookingNotificationIds(PDO $db, array $userIds): array
{
    $ids = [];

    $stmt = $db->prepare(
        "SELECT id FROM notifications
         WHERE user_id = :user_id AND type = 'booking_created'"
    );

    foreach (array_unique(array_map('intval', $userIds)) as $userId) {
        $stmt->execute(['user_id' => $userId]);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $ids[(int) $id] = true;
        }
    }

    return $ids;
}

/**
 * Capture newly inserted booking-created notification IDs and register
 * them for cleanup.
 *
 * @return int[]
 */
function captureNewBookingNotificationIds(PDO $db, array $userIds, array $before): array
{
    global $createdNotifications;

    $after = snapshotBookingNotificationIds($db, $userIds);
    $newIds = [];

    foreach (array_keys($after) as $id) {
        if (!isset($before[$id])) {
            $newIds[] = (int) $id;
            $createdNotifications[(int) $id] = true;
        }
    }

    sort($newIds);
    return $newIds;
}

/**
 * Fetch only notifications created during the current test step for a
 * specific recipient.
 */
function getNewNotificationsForUser(PDO $db, array $newIds, int $userId): array
{
    if ($newIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($newIds), '?'));
    $stmt = $db->prepare(
        "SELECT id, user_id, title, message, type
         FROM notifications
         WHERE id IN ({$placeholders})
           AND user_id = ?
           AND type = 'booking_created'
         ORDER BY id"
    );

    $params = array_values(array_map('intval', $newIds));
    $params[] = $userId;
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Find the appointment created by this test step.
 */
function findNewTestAppointment(
    PDO $db,
    int $customerId,
    int $barberId,
    string $date,
    string $time,
    string $location,
    int $previousMaxId
): ?array {
    $stmt = $db->prepare(
        "SELECT id, status
         FROM appointments
         WHERE customer_id = :customer_id
           AND barber_id = :barber_id
           AND appointment_date = :date
           AND appointment_time = :time
           AND service_location = :location
           AND id > :previous_id
         ORDER BY id DESC
         LIMIT 1"
    );

    $stmt->execute([
        'customer_id' => $customerId,
        'barber_id' => $barberId,
        'date' => $date,
        'time' => $time . ':00',
        'location' => $location,
        'previous_id' => $previousMaxId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

try {
    if (!$barber || !$shop) {
        throw new RuntimeException(
            'Test setup failed: barber 1 or shop 1 was not found.'
        );
    }

    $barberUserId = (int) $barber->userId;
    $shopOwnerId = (int) $shop->ownerId;

    if ($barberUserId <= 0 || $shopOwnerId <= 0) {
        throw new RuntimeException(
            'Test setup failed: barber or shop owner user ID is invalid.'
        );
    }

    $date = (new DateTimeImmutable(
        'next Tuesday',
        new DateTimeZone('Africa/Accra')
    ))->format('Y-m-d');

    /*
     * Ensure a HOME schedule exists on Tuesday and spans both test times.
     * Any existing schedule is restored in finally; a temporary row is
     * deleted there.
     */
    $stmt = $bookingDb->prepare(
        "SELECT id, start_time, end_time
         FROM barber_schedule
         WHERE barber_id = :barber_id
           AND day = 'Tuesday'
           AND service_location = 'HOME'
         LIMIT 1"
    );
    $stmt->execute(['barber_id' => $barberId]);
    $originalHomeSchedule = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($originalHomeSchedule !== null) {
        $stmt = $bookingDb->prepare(
            "UPDATE barber_schedule
             SET start_time = '09:00:00', end_time = '21:00:00'
             WHERE id = :id AND barber_id = :barber_id
               AND day = 'Tuesday' AND service_location = 'HOME'"
        );
        $stmt->execute([
            'id' => $originalHomeSchedule['id'],
            'barber_id' => $barberId,
        ]);
        $homeScheduleChanged = true;
    } else {
        $stmt = $bookingDb->prepare(
            "INSERT INTO barber_schedule
                (barber_id, day, service_location, start_time, end_time)
             VALUES
                (:barber_id, 'Tuesday', 'HOME', '09:00:00', '21:00:00')"
        );
        $stmt->execute(['barber_id' => $barberId]);
        $temporaryHomeScheduleId = (int) $bookingDb->lastInsertId();

        if ($temporaryHomeScheduleId <= 0) {
            throw new RuntimeException('Could not create the temporary HOME schedule.');
        }
    }

    $bookingService = new BookingService(
        new ShopRepository(),
        new BarberRepository(),
        new ServiceRepository(),
        new BarberHomeServiceRepository(),
        new AppointmentRepository(),
        new BarberScheduleRepository(),
        new SubscriptionRepository(),
        new CustomerBookingRestrictionRepository(),
        new NotificationRepository()
    );

    /*
     * TEST 1: SHOP booking. Snapshot notifications immediately before
     * the booking so older notifications cannot make this test pass.
     */
    $shopNotificationsBefore = snapshotBookingNotificationIds(
        $notificationDb,
        [$barberUserId, $shopOwnerId]
    );
    $beforeAppointmentId = (int) $bookingDb->query(
        'SELECT COALESCE(MAX(id), 0) FROM appointments'
    )->fetchColumn();

    $bookingService->createAppointment([
        'barber_id' => $barberId,
        'service_id' => $shopServiceId,
        'service_location' => 'SHOP',
        'appointment_date' => $date,
        'appointment_time' => '10:00',
    ], $shopCustomerId);

    $shopAppointment = findNewTestAppointment(
        $bookingDb,
        $shopCustomerId,
        $barberId,
        $date,
        '10:00',
        'SHOP',
        $beforeAppointmentId
    );

    if ($shopAppointment !== null) {
        $createdAppointments[] = [
            'id' => (int) $shopAppointment['id'],
            'customer_id' => $shopCustomerId,
            'barber_id' => $barberId,
            'date' => $date,
            'time' => '10:00:00',
            'location' => 'SHOP',
        ];
    }

    bookingNotificationCheck('SHOP booking was created', $shopAppointment !== null);
    bookingNotificationCheck(
        'SHOP booking was confirmed',
        $shopAppointment !== null && $shopAppointment['status'] === 'confirmed'
    );

    $shopNewNotificationIds = captureNewBookingNotificationIds(
        $notificationDb,
        [$barberUserId, $shopOwnerId],
        $shopNotificationsBefore
    );

    $shopBarberNotifications = getNewNotificationsForUser(
        $notificationDb,
        $shopNewNotificationIds,
        $barberUserId
    );
    $shopOwnerNotifications = getNewNotificationsForUser(
        $notificationDb,
        $shopNewNotificationIds,
        $shopOwnerId
    );

    bookingNotificationCheck(
        'Barber received a new SHOP booking notification',
        count(array_filter(
            $shopBarberNotifications,
            static fn(array $n): bool =>
                $n['title'] === 'New Appointment'
                && $n['type'] === 'booking_created'
                && str_contains((string) $n['message'], $date)
        )) >= 1
    );

    bookingNotificationCheck(
        'Shop owner received a new SHOP booking notification',
        count(array_filter(
            $shopOwnerNotifications,
            static fn(array $n): bool =>
                $n['title'] === 'New Shop Appointment'
                && $n['type'] === 'booking_created'
                && str_contains((string) $n['message'], $date)
        )) >= 1
    );

    /*
     * TEST 2: HOME booking. The notification snapshot is taken after the
     * SHOP booking so this assertion only examines notifications caused
     * by the HOME booking.
     */
    $homeNotificationsBefore = snapshotBookingNotificationIds(
        $notificationDb,
        [$barberUserId, $shopOwnerId]
    );
    $beforeAppointmentId = (int) $bookingDb->query(
        'SELECT COALESCE(MAX(id), 0) FROM appointments'
    )->fetchColumn();

    $bookingService->createAppointment([
        'barber_id' => $barberId,
        'service_id' => $homeServiceId,
        'service_location' => 'HOME',
        'appointment_date' => $date,
        'appointment_time' => '12:00',
    ], $homeCustomerId);

    $homeAppointment = findNewTestAppointment(
        $bookingDb,
        $homeCustomerId,
        $barberId,
        $date,
        '12:00',
        'HOME',
        $beforeAppointmentId
    );

    if ($homeAppointment !== null) {
        $createdAppointments[] = [
            'id' => (int) $homeAppointment['id'],
            'customer_id' => $homeCustomerId,
            'barber_id' => $barberId,
            'date' => $date,
            'time' => '12:00:00',
            'location' => 'HOME',
        ];
    }

    bookingNotificationCheck('HOME booking was created', $homeAppointment !== null);

    $homeNewNotificationIds = captureNewBookingNotificationIds(
        $notificationDb,
        [$barberUserId, $shopOwnerId],
        $homeNotificationsBefore
    );

    $homeBarberNotifications = getNewNotificationsForUser(
        $notificationDb,
        $homeNewNotificationIds,
        $barberUserId
    );

    bookingNotificationCheck(
        'Barber received a new HOME booking notification',
        count(array_filter(
            $homeBarberNotifications,
            static fn(array $n): bool =>
                $n['title'] === 'New Appointment'
                && $n['type'] === 'booking_created'
                && str_contains((string) $n['message'], $date)
                && stripos((string) $n['message'], 'home') !== false
        )) >= 1
    );

    echo "[INFO] Notification-failure isolation is implemented with independent "
        . "try/catch blocks in BookingService; this test does not inject a "
        . "database failure.\n";
} catch (Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $failed++;
} finally {
    /*
     * Cleanup only records this test explicitly tracked. Keep schedule
     * restoration outside notification cleanup so it always runs.
     */
    foreach (array_reverse($createdAppointments) as $appointment) {
        $stmt = $bookingDb->prepare(
            "DELETE FROM appointments
             WHERE id = :id
               AND customer_id = :customer_id
               AND barber_id = :barber_id
               AND appointment_date = :date
               AND appointment_time = :time
               AND service_location = :location"
        );

        $stmt->execute([
            'id' => $appointment['id'],
            'customer_id' => $appointment['customer_id'],
            'barber_id' => $appointment['barber_id'],
            'date' => $appointment['date'],
            'time' => $appointment['time'],
            'location' => $appointment['location'],
        ]);

        if ($stmt->rowCount() === 1) {
            echo "[CLEANUP] Removed test appointment {$appointment['id']}\n";
        }
    }

    foreach (array_keys($createdNotifications) as $notificationId) {
        $stmt = $notificationDb->prepare(
            "DELETE FROM notifications
             WHERE id = :id AND type = 'booking_created'"
        );
        $stmt->execute(['id' => $notificationId]);

        if ($stmt->rowCount() === 1) {
            echo "[CLEANUP] Removed test notification {$notificationId}\n";
        }
    }

    if ($homeScheduleChanged && $originalHomeSchedule !== null) {
        $stmt = $bookingDb->prepare(
            "UPDATE barber_schedule
             SET start_time = :start_time, end_time = :end_time
             WHERE id = :id AND barber_id = :barber_id
               AND day = 'Tuesday' AND service_location = 'HOME'"
        );
        $stmt->execute([
            'start_time' => $originalHomeSchedule['start_time'],
            'end_time' => $originalHomeSchedule['end_time'],
            'id' => $originalHomeSchedule['id'],
            'barber_id' => $barberId,
        ]);
        echo "[CLEANUP] Restored original HOME schedule\n";
    }

    if ($temporaryHomeScheduleId !== null) {
        $stmt = $bookingDb->prepare(
            "DELETE FROM barber_schedule
             WHERE id = :id AND barber_id = :barber_id
               AND day = 'Tuesday' AND service_location = 'HOME'"
        );
        $stmt->execute([
            'id' => $temporaryHomeScheduleId,
            'barber_id' => $barberId,
        ]);

        if ($stmt->rowCount() === 1) {
            echo "[CLEANUP] Removed temporary HOME schedule\n";
        }
    }

    echo "\nResults: {$passed} passed, {$failed} failed\n";
}