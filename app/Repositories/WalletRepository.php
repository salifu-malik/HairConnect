<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use PDO;

class WalletRepository {
    private PDO $db;

    public function __construct() {
        $this->db = DatabaseManager::getConnection('wallet_db');
    }

    public function getWalletByUserId(int $userId) {
        $stmt = $this->db->prepare("SELECT * FROM wallets WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function credit(int $walletId, float $amount, string $reference, string $description): bool {
        $this->db->beginTransaction();
        try {
            // Update balance
            $stmt = $this->db->prepare("UPDATE wallets SET available_balance = available_balance + :amount, total_earned = total_earned + :amount WHERE id = :wallet_id");
            $stmt->execute(['amount' => $amount, 'wallet_id' => $walletId]);

            // Log transaction
            $stmt = $this->db->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, reference, description) VALUES (:wallet_id, 'CREDIT', :amount, :reference, :description)");
            $stmt->execute(['wallet_id' => $walletId, 'amount' => $amount, 'reference' => $reference, 'description' => $description]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
}
