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


     //Create an appointment.
    public function create(array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO appointments (
                customer_id,
                shop_id,
                barber_id,
                service_id,
                appointment_date,
                appointment_time
            )
            VALUES (
                :customer_id,
                :shop_id,
                :barber_id,
                :service_id,
                :appointment_date,
                :appointment_time
            )
        ");

        return $stmt->execute([
            'customer_id' => $data['customer_id'],
            'shop_id' => $data['shop_id'] ?? null,
            'barber_id' => $data['barber_id'],
            'service_id' => $data['service_id'],
            'appointment_date' => $data['appointment_date'],
            'appointment_time' => $data['appointment_time'],
        ]);
    }

    /**
     * Check whether a barber already has an appointment
     * that overlaps the requested time range.
     *
     * $startTime and $endTime must be in HH:MM format.
     */
    public function hasConflict(
        int $barberId,
        string $appointmentDate,
        string $startTime,
        string $endTime
    ): bool {
        $stmt = $this->db->prepare("
            SELECT
                id,
                appointment_time
            FROM appointments
            WHERE barber_id = :barber_id
              AND appointment_date = :appointment_date
              AND status IN ('pending', 'confirmed')
              AND appointment_time < :end_time
            LIMIT 1
        ");

        $stmt->execute([
            'barber_id' => $barberId,
            'appointment_date' => $appointmentDate,
            'end_time' => $startTime,
        ]);

        $appointments = $stmt->fetchAll();

        /*
         * The initial query above only identifies appointments
         * that start before the requested end time.
         *
         * The actual appointment duration is not stored in the
         * appointments table, so duration-based conflict checking
         * will be completed in BookingService using the selected
         * service duration.
         */

        return !empty($appointments);
    }
}
