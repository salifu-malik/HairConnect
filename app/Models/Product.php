<?php

namespace App\Models;

class Product {
    public int $id;
    public int $sellerId;
    public int $categoryId;
    public string $name;
    public ?string $description;
    public float $costPrice;
    public float $sellingPrice;
    public int $stockQuantity;
    public ?string $image;
    public string $status;

    public function __construct(array $data) {
        $this->id = $data['id'] ?? 0;
        $this->sellerId = $data['seller_id'] ?? 0;
        $this->categoryId = $data['category_id'] ?? 0;
        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? null;
        $this->costPrice = (float)($data['cost_price'] ?? 0.0);
        $this->sellingPrice = (float)($data['selling_price'] ?? 0.0);
        $this->stockQuantity = $data['stock_quantity'] ?? 0;
        $this->image = $data['image'] ?? null;
        $this->status = $data['status'] ?? 'active';
    }
}
