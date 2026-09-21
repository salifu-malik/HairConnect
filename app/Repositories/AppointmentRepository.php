<?php

namespace App\Repositories;

use App\Models\Appointment;
use App\Helpers\DatabaseManager;
use PDO;

class AppointmentRepository {
    private PDO $db;

    public function __construct() {
        $this->db = DatabaseManager::getConnection('booking_db');
    }

    public function create(array $data): bool {
        $stmt = $this->db->prepare("
            INSERT INTO appointments (customer_id, shop_id, barber_id, service_id, appointment_date, appointment_time)
            VALUES (:customer_id, :shop_id, :barber_id, :service_id, :appointment_date, :appointment_time)
        ");
        return $stmt->execute([
            'customer_id' => $data['customer_id'],
            'shop_id' => $data['shop_id'],
            'barber_id' => $data['barber_id'],
            'service_id' => $data['service_id'],
            'appointment_date' => $data['appointment_date'],
            'appointment_time' => $data['appointment_time'],
        ]);
    }
}
