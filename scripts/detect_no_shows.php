<?php

declare(strict_types=1);

use App\Repositories\AppointmentRepository;
use App\Repositories\NotificationRepository;

require_once __DIR__ . '/../bootstrap.php';

date_default_timezone_set('Africa/Accra');

$now = new DateTimeImmutable(
    'now',
    new DateTimeZone('Africa/Accra')
);

$currentDateTime = $now->format('Y-m-d H:i:s');

$appointmentRepository = new AppointmentRepository();
$notificationRepository = new NotificationRepository();

$processed = 0;
$notificationsSent = 0;
$errors = 0;

try {
    $appointments = $appointmentRepository
        ->findEligibleNoShowAppointments($currentDateTime);

    foreach ($appointments as $appointment) {
        $appointmentId = (int) $appointment['appointment_id'];
        $shopOwnerId = (int) $appointment['owner_id'];

        try {
            $updated = $appointmentRepository
                ->markAppointmentAsNoShow(
                    $appointmentId,
                    $currentDateTime
                );

            if (!$updated) {
                // The appointment may have changed since selection.
                continue;
            }

            $processed++;

            try {
                $notificationRepository->createNotification(
                    $shopOwnerId,
                    'Appointment marked as no-show',
                    sprintf(
                        'Appointment #%d at %s has been automatically marked as a no-show because the customer did not check in within the allowed grace period.',
                        $appointmentId,
                        $appointment['shop_name']
                    ),
                    'appointment_no_show'
                );

                $notificationsSent++;

            } catch (Throwable $notificationError) {
                $errors++;

                error_log(sprintf(
                    '[NO_SHOW] Appointment #%d was marked as no-show, but its notification failed: %s',
                    $appointmentId,
                    $notificationError->getMessage()
                ));
            }

        } catch (Throwable $appointmentError) {
            $errors++;

            error_log(sprintf(
                '[NO_SHOW] Failed to process appointment #%d: %s',
                $appointmentId,
                $appointmentError->getMessage()
            ));
        }
    }

    echo sprintf(
        "[%s] No-show scan complete. Candidates: %d, marked: %d, notifications sent: %d, errors: %d\n",
        $currentDateTime,
        count($appointments),
        $processed,
        $notificationsSent,
        $errors
    );

} catch (Throwable $error) {
    fwrite(
        STDERR,
        sprintf(
            "[%s] No-show detection failed: %s\n",
            $currentDateTime,
            $error->getMessage()
        )
    );

    exit(1);
}