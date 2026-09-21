<?php

namespace App\Repositories;

use App\Models\Product;
use App\Helpers\DatabaseManager;
use PDO;

class ProductRepository {
    private PDO $db;

    public function __construct() {
        $this->db = DatabaseManager::getConnection('store_db');
    }

    public function create(array $data): bool {
        $stmt = $this->db->prepare("
            INSERT INTO products (seller_id, category_id, name, description, cost_price, selling_price, stock_quantity, image)
            VALUES (:seller_id, :category_id, :name, :description, :cost_price, :selling_price, :stock_quantity, :image)
        ");
        return $stmt->execute([
            'seller_id' => $data['seller_id'],
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'cost_price' => $data['cost_price'],
            'selling_price' => $data['selling_price'],
            'stock_quantity' => $data['stock_quantity'] ?? 0,
            'image' => $data['image'] ?? null,
        ]);
    }

    public function findAll(): array {
        $stmt = $this->db->query("SELECT * FROM products WHERE status = 'active'");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
