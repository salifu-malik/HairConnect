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
                appointment_time
            )
            VALUES (
                :customer_id,
                :shop_id,
                :barber_id,
                :service_id,
                :service_location,
                :duration_minutes,
                :appointment_date,
                :appointment_time
            )
        ");

        return $stmt->execute([
            'customer_id' => $data['customer_id'],
            'shop_id' => $data['shop_id'],
            'barber_id' => $data['barber_id'],
            'service_id' => $data['service_id'],
            'service_location' => $data['service_location'],
            'duration_minutes' => $data['duration_minutes'],
            'appointment_date' => $data['appointment_date'],
            'appointment_time' => $data['appointment_time'],
        ]);
    }

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
            'barber_id' => $barberId,
            'appointment_date' => $appointmentDate,
        ]);

        return $stmt->fetchAll();
    }


    //Start a booking transaction.
    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    /**
     * Lock the booking resource for one barber on one date.
     *
     * The lock is deliberately scoped to:
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
         * requests for the same barber/date to safely converge
         * on the same lock row.
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
            'barber_id' => $barberId,
            'booking_date' => $bookingDate,
        ]);

        /*
         * Acquire an exclusive row lock.
         *
         * Any concurrent transaction attempting to book the
         * same barber on the same date must wait here until
         * this transaction commits or rolls back.
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
            'barber_id' => $barberId,
            'booking_date' => $bookingDate,
        ]);

        if (!$lockStmt->fetch()) {
            throw new \RuntimeException(
                'Unable to acquire booking lock.'
            );
        }
    }


    //Commit the current booking transaction.
    public function commit(): void
    {
        $this->db->commit();
    }


     //Roll back the current booking transaction.
    public function rollback(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }
}