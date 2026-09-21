<?php

namespace App\Models;

class Notification {
    public int $id;
    public int $userId;
    public string $title;
    public string $message;
    public string $type;
    public string $status;

    public function __construct(array $data) {
        $this->id = $data['id'] ?? 0;
        $this->userId = $data['user_id'] ?? 0;
        $this->title = $data['title'] ?? '';
        $this->message = $data['message'] ?? '';
        $this->type = $data['type'] ?? '';
        $this->status = $data['status'] ?? 'unread';
    }
}
