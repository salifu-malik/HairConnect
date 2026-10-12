<?php

namespace App\Repositories;

use App\Models\Order;
use App\Helpers\DatabaseManager;
use PDO;

class OrderRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection('store_db');
    }

    public function create(array $data): bool
    {
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

    public function createOrderItem(array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO order_items
                (order_id, product_id, quantity, price)
            VALUES
                (:order_id, :product_id, :quantity, :price)
        ");

        return $stmt->execute([
            'order_id' => $data['order_id'],
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'price' => $data['price'],
        ]);
    }

    /**
     * Retrieve orders belonging to a specific customer.
     *
     * Each order includes its associated items and product details.
     */
    public function findByCustomerId(int $customerId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                customer_id,
                total_amount,
                status,
                created_at
            FROM orders
            WHERE customer_id = :customer_id
            ORDER BY created_at DESC, id DESC
        ");

        $stmt->execute([
            'customer_id' => $customerId,
        ]);

        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$orders) {
            return [];
        }

        $itemStmt = $this->db->prepare("
            SELECT
                oi.id,
                oi.order_id,
                oi.product_id,
                oi.quantity,
                oi.price,
                p.name AS product_name,
                p.image AS product_image
            FROM order_items oi
            LEFT JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = :order_id
            ORDER BY oi.id ASC
        ");

        foreach ($orders as &$order) {
            $itemStmt->execute([
                'order_id' => (int) $order['id'],
            ]);

            $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        unset($order);

        return $orders;
    }
}