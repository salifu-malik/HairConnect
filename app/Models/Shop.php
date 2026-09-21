<?php

namespace App\Models;

class Shop
{
    public int $id;
    public int $ownerId;
    public string $name;
    public string $location;
    public ?string $description;

    public string $approvalStatus;
    public ?string $approvedAt;
    public ?int $approvedBy;

    public string $status;

    public function __construct(array $data)
    {
        $this->id = (int) ($data['id'] ?? 0);

        $this->ownerId = (int) ($data['owner_id'] ?? 0);

        $this->name = $data['name'] ?? '';

        $this->location = $data['location'] ?? '';

        $this->description = $data['description'] ?? null;

        $this->approvalStatus =
            $data['approval_status'] ?? 'pending';

        $this->approvedAt =
            $data['approved_at'] ?? null;

        $this->approvedBy =
            isset($data['approved_by'])
                ? (int) $data['approved_by']
                : null;

        $this->status =
            $data['status'] ?? 'inactive';
    }
}