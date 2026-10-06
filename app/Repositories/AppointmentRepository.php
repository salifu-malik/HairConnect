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
     * The caller is responsible for validating the appointment
     * and handling the booking transaction/locking.
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
            booking_code
        )
        VALUES (
            :customer_id,
            :shop_id,
            :barber_id,
            :service_id,
            :service_location,
            :duration_minutes,
            :appointment_date,
            :appointment_time,
            :booking_code
        )
    ");

        return $stmt->execute([
            'customer_id'      => $data['customer_id'],
            'shop_id'          => $data['shop_id'],
            'barber_id'        => $data['barber_id'],
            'service_id'       => $data['service_id'],
            'service_location' => $data['service_location'],
            'duration_minutes' => $data['duration_minutes'],
            'appointment_date' => $data['appointment_date'],
            'appointment_time' => $data['appointment_time'],
            'booking_code'     => $data['booking_code'] ?? null,
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
              AND status IN ('pending', 'confirmed')
            ORDER BY appointment_time ASC
        ");

        $stmt->execute([
            'barber_id'       => $barberId,
            'appointment_date' => $appointmentDate,
        ]);

        return $stmt->fetchAll();
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
                s.name AS service_name,
                s.price AS service_price,
                a.service_location,
                a.duration_minutes,
                a.appointment_date,
                a.appointment_time,
                a.status,
                a.booking_code,
                sh.name AS shop_name,
                sh.location AS shop_location
            FROM appointments a

            INNER JOIN barbers b
                ON a.barber_id = b.id

            INNER JOIN users_db.users u
                ON b.user_id = u.id

            INNER JOIN services s
                ON a.service_id = s.id

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
              AND status IN ('pending', 'confirmed')
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
          AND status IN ('pending', 'confirmed')
        ORDER BY appointment_date ASC, appointment_time ASC
        LIMIT 1
    ");

        $stmt->execute([
            ':customer_id' => $customerId
        ]);

        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        return $appointment ?: null;
    }


}