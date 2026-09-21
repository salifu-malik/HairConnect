<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use PDO;

class PaymentRepository {
    private PDO $db;

    public function __construct() {
        $this->db = DatabaseManager::getConnection('payment_db');
    }

    public function createTransaction(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO transactions (user_id, order_id, reference, amount, status)
            VALUES (:user_id, :order_id, :reference, :amount, :status)
        ");
        $stmt->execute([
            'user_id' => $data['user_id'],
            'order_id' => $data['order_id'],
            'reference' => $data['reference'],
            'amount' => $data['amount'],
            'status' => $data['status'] ?? 'pending',
        ]);
        return (int)$this->db->lastInsertId();
    }
}
