<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use PDO;

class DeliveryRepository {
    private PDO $db;

    public function __construct() {
        $this->db = DatabaseManager::getConnection('delivery_db');
    }

    public function createDelivery(int $orderId, int $deliveryPersonId): int {
        $stmt = $this->db->prepare("
            INSERT INTO deliveries (order_id, delivery_person_id, status)
            VALUES (:order_id, :delivery_person_id, 'pending')
        ");
        $stmt->execute([
            'order_id' => $orderId,
            'delivery_person_id' => $deliveryPersonId,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function logTracking(int $deliveryId, float $lat, float $lon, string $status): bool {
        $stmt = $this->db->prepare("
            INSERT INTO delivery_tracking (delivery_id, latitude, longitude, status)
            VALUES (:delivery_id, :lat, :lon, :status)
        ");
        return $stmt->execute([
            'delivery_id' => $deliveryId,
            'lat' => $lat,
            'lon' => $lon,
            'status' => $status
        ]);
    }
}
