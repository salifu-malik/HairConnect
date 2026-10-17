<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use PDO;

class AppointmentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection('booking_db');
    }

    /**
     * Create a new appointment.
     *
     * Home-service contact and address details are stored as an
     * appointment-specific snapshot. Shop appointments do not need them.
     */
    public function create(array $data): bool
    {
        $stmt = $this->db->prepare("
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
            booking_code,
            home_service_customer_name,
            home_service_phone,
            home_service_region,
            home_service_address,
            home_service_directions
        ) VALUES (
            :customer_id,
            :shop_id,
            :barber_id,
            :service_id,
            :service_location,
            :duration_minutes,
            :appointment_date,
            :appointment_time,
            :status,
            :booking_code,
            :home_service_customer_name,
            :home_service_phone,
            :home_service_region,
            :home_service_address,
            :home_service_directions
        )
    ");

        $isHomeService = strtoupper(
                trim((string) ($data['service_location'] ?? ''))
            ) === 'HOME';

        return $stmt->execute([
            'customer_id' => $data['customer_id'],
            'shop_id' => $data['shop_id'] ?? null,
            'barber_id' => $data['barber_id'],
            'service_id' => $data['service_id'],
            'service_location' => $data['service_location'],
            'duration_minutes' => $data['duration_minutes'],
            'appointment_date' => $data['appointment_date'],
            'appointment_time' => $data['appointment_time'],
            'status' => $data['status'] ?? 'confirmed',
            'booking_code' => $data['booking_code'] ?? null,

            'home_service_customer_name' => $isHomeService
                ? ($data['home_service_customer_name'] ?? null)
                : null,

            'home_service_phone' => $isHomeService
                ? ($data['home_service_phone'] ?? null)
                : null,

            'home_service_region' => $isHomeService
                ? ($data['home_service_region'] ?? null)
                : null,

            'home_service_address' => $isHomeService
                ? ($data['home_service_address'] ?? null)
                : null,

            'home_service_directions' => $isHomeService
                ? ($data['home_service_directions'] ?? null)
                : null,
        ]);
    }



    /**
     * Find all active appointments for a barber on a specific date.
     *
     * Both SHOP and HOME appointments are returned because they
     * compete for the same barber's time.
     */
    public function findByBarberAndDate(
        int $barberId,
        string $appointmentDate
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                customer_id,
                shop_id,
                barber_id,
                service_id,
                service_location,
                duration_minutes,
                appointment_date,
                appointment_time,
                status
            FROM appointments
            WHERE barber_id = :barber_id
            AND appointment_date = :appointment_date
            AND status IN ('pending', 'confirmed', 'checked_in', 'in_progress')
            ORDER BY appointment_time ASC
        ");

        $stmt->execute([
            'barber_id'       => $barberId,
            'appointment_date' => $appointmentDate,
        ]);

        return $stmt->fetchAll();
    }



    /**
     * Find an appointment by ID.
     */
    public function findById(int $appointmentId): ?array
    {
        $stmt = $this->db->prepare("
        SELECT
            id,
            customer_id,
            shop_id,
            barber_id,
            service_id,
            service_location,
            duration_minutes,
            appointment_date,
            appointment_time,
            status,
            booking_code,
            checked_in_at,
            checked_in_by,
            completed_at
        FROM appointments
        WHERE id = :appointment_id
        LIMIT 1
    ");

        $stmt->execute([
            'appointment_id' => $appointmentId,
        ]);

        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        return $appointment ?: null;
    }



    /**
     * Confirm a pending appointment.
     *
     * Allowed transition:
     *
     * pending → confirmed
     */
    public function confirmAppointment(int $appointmentId): bool
    {
        $stmt = $this->db->prepare("
        UPDATE appointments
        SET
            status = 'confirmed'
        WHERE id = :appointment_id
          AND status = 'pending'
    ");

        $stmt->execute([
            'appointment_id' => $appointmentId,
        ]);

        return $stmt->rowCount() === 1;
    }


    /**
     * Cancel an appointment.
     * Allowed transitions:
     * pending → cancelled
     * confirmed → cancelled
     */
    public function cancelAppointment(int $appointmentId): bool
    {
        $stmt = $this->db->prepare("
        UPDATE appointments
        SET
            status = 'cancelled'
        WHERE id = :appointment_id
          AND status IN ('pending', 'confirmed')
    ");

        $stmt->execute([
            'appointment_id' => $appointmentId,
        ]);

        return $stmt->rowCount() === 1;
    }


    /**
     * Check in a confirmed appointment.
     *
     * Allowed transition:
     *
     * confirmed → checked_in
     */
    public function checkInAppointment(
        int $appointmentId,
        int $checkedInBy
    ): bool {
        $stmt = $this->db->prepare("
        UPDATE appointments
        SET
            status = 'checked_in',
            checked_in_at = CURRENT_TIMESTAMP,
            checked_in_by = :checked_in_by
        WHERE id = :appointment_id
          AND status = 'confirmed'
    ");

        $stmt->execute([
            'appointment_id' => $appointmentId,
            'checked_in_by'  => $checkedInBy,
        ]);

        return $stmt->rowCount() === 1;
    }



    /**
     * Start the service for a checked-in appointment.
     *
     * Allowed transition:
     *
     * checked_in → in_progress
     */
    public function startAppointment(int $appointmentId): bool
    {
        $sql = "
        UPDATE appointments
        SET status = 'in_progress'
        WHERE id = :id
          AND (
                (
                    service_location = 'SHOP'
                    AND status = 'checked_in'
                )
                OR
                (
                    service_location = 'HOME'
                    AND status = 'confirmed'
                )
          )
    ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':id' => $appointmentId,
        ]);

        return $stmt->rowCount() === 1;
    }






    /**
     * Complete an appointment.
     *
     * Allowed transition:
     *
     * in_progress → completed
     *
     * The completion timestamp is recorded by the database.
     */
    public function completeAppointment(int $appointmentId): bool
    {
        $stmt = $this->db->prepare("
        UPDATE appointments
        SET
            status = 'completed',
            completed_at = CURRENT_TIMESTAMP
        WHERE id = :appointment_id
          AND status = 'in_progress'
    ");

        $stmt->execute([
            'appointment_id' => $appointmentId,
        ]);

        return $stmt->rowCount() === 1;
    }




    /**
     * Find all appointments belonging to a customer.
     */
    public function findByCustomer(int $customerId): array
    {
        $stmt = $this->db->prepare("
            SELECT
        a.id,
        a.customer_id,
        a.shop_id,
        a.barber_id,

        CONCAT(u.first_name, ' ', u.last_name) AS barber_name,
        u.profile_image AS barber_profile_image,

        a.service_id,

        CASE
            WHEN a.service_location = 'HOME'
                THEN hs.name
            ELSE s.name
        END AS service_name,

        CASE
            WHEN a.service_location = 'HOME'
                THEN hs.price
            ELSE s.price
        END AS service_price,

        a.service_location,
        a.duration_minutes,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.booking_code,

        sh.name AS shop_name,
        sh.location AS shop_location,

        a.home_service_customer_name,
        a.home_service_phone,
        a.home_service_region,
        a.home_service_address,
        a.home_service_directions

    FROM appointments a

    INNER JOIN barbers b
        ON a.barber_id = b.id

    INNER JOIN users_db.users u
        ON b.user_id = u.id

    LEFT JOIN services s
        ON a.service_location = 'SHOP'
        AND a.service_id = s.id

    LEFT JOIN barber_home_services hs
        ON a.service_location = 'HOME'
        AND a.service_id = hs.id

    LEFT JOIN shops sh
        ON a.shop_id = sh.id

    WHERE a.customer_id = :customer_id

    ORDER BY
        a.appointment_date DESC,
        a.appointment_time DESC
        ");

        $stmt->execute([
            'customer_id' => $customerId,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Find all appointments belonging to a shop.
     *
     * Authorization is intentionally NOT handled here.
     * The service layer verifies that the requesting user owns
     * the shop before calling this method.
     */
    public function findByShop(int $shopId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.id,

                a.customer_id,
                CONCAT(cu.first_name, ' ', cu.last_name) AS customer_name,
                cu.phone AS customer_phone,
                cu.profile_image AS customer_profile_image,

                a.shop_id,

                a.barber_id,
                CONCAT(bu.first_name, ' ', bu.last_name) AS barber_name,
                bu.profile_image AS barber_profile_image,

                a.service_id,
                s.name AS service_name,
                s.price AS service_price,

                a.service_location,
                a.duration_minutes,
                a.appointment_date,
                a.appointment_time,
                a.status,

                sh.name AS shop_name,
                sh.location AS shop_location

            FROM appointments a

            INNER JOIN users_db.users cu
                ON a.customer_id = cu.id

            INNER JOIN barbers b
                ON a.barber_id = b.id

            INNER JOIN users_db.users bu
                ON b.user_id = bu.id

            INNER JOIN services s
                ON a.service_id = s.id

            INNER JOIN shops sh
                ON a.shop_id = sh.id

            WHERE a.shop_id = :shop_id

            ORDER BY
                a.appointment_date ASC,
                a.appointment_time ASC
        ");

        $stmt->execute([
            'shop_id' => $shopId,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Find all appointments assigned to a specific barber.
     *
     * Supports both SHOP and HOME appointments.
     */
    public function findByBarber(int $barberId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            a.id,
            a.customer_id,

            CONCAT(cu.first_name, ' ', cu.last_name) AS customer_name,
            cu.phone AS customer_phone,
            cu.profile_image AS customer_profile_image,

            a.shop_id,
            a.barber_id,

            a.service_id,

            CASE
                WHEN a.service_location = 'HOME'
                    THEN hs.name
                ELSE s.name
            END AS service_name,

            CASE
                WHEN a.service_location = 'HOME'
                    THEN hs.price
                ELSE s.price
            END AS service_price,

            a.service_location,
            a.duration_minutes,
            a.appointment_date,
            a.appointment_time,
            a.status,
            a.booking_code,

            sh.name AS shop_name,
            sh.location AS shop_location,

            a.home_service_customer_name,
            a.home_service_phone,
            a.home_service_region,
            a.home_service_address,
            a.home_service_directions

        FROM appointments a

        INNER JOIN users_db.users cu
            ON a.customer_id = cu.id

        LEFT JOIN services s
            ON a.service_location = 'SHOP'
            AND a.service_id = s.id

        LEFT JOIN barber_home_services hs
            ON a.service_location = 'HOME'
            AND a.service_id = hs.id

        LEFT JOIN shops sh
            ON a.shop_id = sh.id

        WHERE a.barber_id = :barber_id

        ORDER BY
            a.appointment_date ASC,
            a.appointment_time ASC
    ");

        $stmt->execute([
            'barber_id' => $barberId,
        ]);

        return $stmt->fetchAll();
    }



    /**
     * Count appointments belonging to a shop.
     */
    public function countByShop(int $shopId): int
    {
        $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM appointments
        WHERE shop_id = :shop_id
    ");

        $stmt->execute([
            'shop_id' => $shopId,
        ]);

        return (int) $stmt->fetchColumn();
    }


    /**
     * Find all active/booked appointments for a barber on a date.
     *
     * This is used by the availability system to determine which
     * candidate time slots are already occupied.
     *
     * Both SHOP and HOME appointments are intentionally returned.
     */
    public function findBookedByBarberAndDate(
        int $barberId,
        string $appointmentDate
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                barber_id,
                service_location,
                duration_minutes,
                appointment_date,
                appointment_time,
                status
            FROM appointments
            WHERE barber_id = :barber_id
            AND appointment_date = :appointment_date
            AND status IN ('pending', 'confirmed', 'checked_in', 'in_progress')
            ORDER BY appointment_time ASC
        ");

        $stmt->execute([
            'barber_id'        => $barberId,
            'appointment_date' => $appointmentDate,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Start a booking transaction.
     */
    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    /**
     * Lock the booking resource for one barber on one date.
     *
     * The lock is deliberately scoped to:
     *
     *     barber_id + booking_date
     *
     * This protects the barber's entire calendar for that date,
     * including both SHOP and HOME appointments.
     */
    public function lockBarberDate(
        int $barberId,
        string $bookingDate
    ): void {
        /*
         * Create the lock row if it does not already exist.
         *
         * ON DUPLICATE KEY UPDATE allows concurrent booking
         * requests for the same barber/date to converge on
         * the same lock row.
         */
        $insertStmt = $this->db->prepare("
            INSERT INTO barber_booking_locks (
                barber_id,
                booking_date
            )
            VALUES (
                :barber_id,
                :booking_date
            )
            ON DUPLICATE KEY UPDATE
                booking_date = VALUES(booking_date)
        ");

        $insertStmt->execute([
            'barber_id'    => $barberId,
            'booking_date' => $bookingDate,
        ]);

        /*
         * Acquire an exclusive row lock.
         *
         * Any concurrent transaction attempting to book the
         * same barber on the same date must wait until the
         * current transaction commits or rolls back.
         */
        $lockStmt = $this->db->prepare("
            SELECT
                barber_id,
                booking_date
            FROM barber_booking_locks
            WHERE barber_id = :barber_id
              AND booking_date = :booking_date
            FOR UPDATE
        ");

        $lockStmt->execute([
            'barber_id'    => $barberId,
            'booking_date' => $bookingDate,
        ]);

        if (!$lockStmt->fetch()) {
            throw new \RuntimeException(
                'Unable to acquire booking lock.'
            );
        }
    }

    /**
     * Commit the current booking transaction.
     */
    public function commit(): void
    {
        $this->db->commit();
    }

    /**
     * Roll back the current booking transaction.
     */
    public function rollback(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }


    public function findActiveByCustomer(int $customerId): ?array
    {
        $stmt = $this->db->prepare("
        SELECT
            id,
            customer_id,
            shop_id,
            barber_id,
            service_id,
            service_location,
            duration_minutes,
            appointment_date,
            appointment_time,
            status
        FROM appointments
        WHERE customer_id = :customer_id
          AND status IN ('pending', 'confirmed', 'checked_in', 'in_progress')
          AND (
              appointment_date > CURDATE()
              OR (
                  appointment_date = CURDATE()
                  AND ADDTIME(
                      appointment_time,
                      SEC_TO_TIME(duration_minutes * 60)
                  ) > CURTIME()
              )
          )
        ORDER BY appointment_date ASC, appointment_time ASC
        LIMIT 1
    ");

        $stmt->execute([
            ':customer_id' => $customerId
        ]);

        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        return $appointment ?: null;
    }


    /**
     * Find confirmed shop appointments whose scheduled end time
     * plus the shop's configured no-show grace period has elapsed.
     *
     * Only confirmed appointments are eligible.
     * Cancelled, checked-in, in-progress and completed appointments
     * are excluded.
     */
    public function findEligibleNoShowAppointments(
        string $currentDateTime
    ): array {
        $stmt = $this->db->prepare("
        SELECT
            a.id AS appointment_id,
            a.customer_id,
            a.barber_id,
            a.shop_id,
            a.appointment_date,
            a.appointment_time,
            a.duration_minutes,
            sh.owner_id,
            sh.name AS shop_name,
            sh.no_show_grace_minutes
        FROM appointments a
        INNER JOIN shops sh
            ON sh.id = a.shop_id
        WHERE a.service_location = 'SHOP'
          AND a.status = 'confirmed'
          AND a.checked_in_at IS NULL
          AND a.duration_minutes > 0
          AND TIMESTAMP(
                a.appointment_date,
                a.appointment_time
              )
              + INTERVAL a.duration_minutes MINUTE
              + INTERVAL sh.no_show_grace_minutes MINUTE
              <= :current_datetime
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");

        $stmt->execute([
            'current_datetime' => $currentDateTime,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Atomically mark an eligible shop appointment as a no-show.
     *
     * The conditional update protects appointments whose status
     * changes after the candidate query.
     */
    public function markAppointmentAsNoShow(
        int $appointmentId,
        string $currentDateTime
    ): bool {
        $stmt = $this->db->prepare("
        UPDATE appointments a
        INNER JOIN shops sh
            ON sh.id = a.shop_id
        SET a.status = 'no_show'
        WHERE a.id = :appointment_id
          AND a.service_location = 'SHOP'
          AND a.status = 'confirmed'
          AND a.checked_in_at IS NULL
          AND a.duration_minutes > 0
          AND TIMESTAMP(
                a.appointment_date,
                a.appointment_time
              )
              + INTERVAL a.duration_minutes MINUTE
              + INTERVAL sh.no_show_grace_minutes MINUTE
              <= :current_datetime
    ");

        $stmt->execute([
            'appointment_id' => $appointmentId,
            'current_datetime' => $currentDateTime,
        ]);

        return $stmt->rowCount() === 1;
    }



}