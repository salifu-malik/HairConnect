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
            'shop_id' => $data['shop_id'] ?? null,
            'barber_id' => $data['barber_id'],
            'service_id' => $data['service_id'],
            'service_location' => $data['service_location'],
            'duration_minutes' => $data['duration_minutes'],
            'appointment_date' => $data['appointment_date'],
            'appointment_time' => $data['appointment_time'],
        ]);
    }

    /**
     * Get all active appointments for a barber
     * on a specific date.
     *
     * Cancelled appointments are excluded because
     * they no longer occupy the barber's schedule.
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
                status,
                created_at,
                updated_at
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
}
