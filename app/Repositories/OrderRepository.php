<?php

namespace App\Repositories;

use App\Models\Order;
use App\Helpers\DatabaseManager;
use PDO;

class OrderRepository {
    private PDO $db;

    public function __construct() {
        $this->db = DatabaseManager::getConnection('store_db');
    }

    public function create(array $data): bool {
        $stmt = $this->db->prepare("
            INSERT INTO orders (customer_id, total_amount, status)
            VALUES (:customer_id, :total_amount, :status)
        ");
        return $stmt->execute([
            'customer_id' => $data['customer_id'],
            'total_amount' => $data['total_amount'],
            'status' => $data['status'] ?? 'pending',
        ]);
    }

    public function createOrderItem(array $data): bool {
        $stmt = $this->db->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, price)
            VALUES (:order_id, :product_id, :quantity, :price)
        ");
        return $stmt->execute([
            'order_id' => $data['order_id'],
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'price' => $data['price'],
        ]);
    }
}
