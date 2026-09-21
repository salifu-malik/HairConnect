<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use PDO;

class BarberScheduleRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection('booking_db');
    }

    /**
     * Find a barber's schedule for a specific day.
     */
    public function findByBarberAndDay(
        int $barberId,
        string $day,
        string $serviceLocation
    ): ?array {
        $stmt = $this->db->prepare("
        SELECT
            id,
            barber_id,
            day,
            service_location,
            start_time,
            end_time,
            created_at,
            updated_at
        FROM barber_schedule
        WHERE barber_id = :barber_id
          AND day = :day
          AND service_location = :service_location
        LIMIT 1
    ");

        $stmt->execute([
            'barber_id' => $barberId,
            'day' => $day,
            'service_location' => $serviceLocation,
        ]);

        $schedule = $stmt->fetch();

        return $schedule ?: null;
    }

    /**
     * Get all schedules belonging to a barber.
     */
    public function findByBarber(int $barberId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            id,
            barber_id,
            day,
            service_location,
            start_time,
            end_time,
            created_at,
            updated_at
        FROM barber_schedule
        WHERE barber_id = :barber_id
        ORDER BY FIELD(
            day,
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday'
        ),
        service_location,
        start_time
    ");

        $stmt->execute([
            'barber_id' => $barberId,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Create a schedule for a barber.
     */
    public function create(array $data): bool
    {
        $stmt = $this->db->prepare("
        INSERT INTO barber_schedule (
            barber_id,
            day,
            service_location,
            start_time,
            end_time
        )
        VALUES (
            :barber_id,
            :day,
            :service_location,
            :start_time,
            :end_time
        )
    ");

        return $stmt->execute([
            'barber_id' => $data['barber_id'],
            'day' => $data['day'],
            'service_location' => $data['service_location'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
        ]);
    }
}