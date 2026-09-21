<?php

namespace App\Models;

class Wallet {
    public int $id;
    public int $userId;
    public float $availableBalance;
    public float $pendingBalance;

    public function __construct(array $data) {
        $this->id = $data['id'] ?? 0;
        $this->userId = $data['user_id'] ?? 0;
        $this->availableBalance = (float)($data['available_balance'] ?? 0.0);
        $this->pendingBalance = (float)($data['pending_balance'] ?? 0.0);
    }
}
