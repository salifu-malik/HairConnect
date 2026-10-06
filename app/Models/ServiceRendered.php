<?php

namespace App\Models;

class ServiceRendered
{
    public ?int $id;

    public int $appointmentId;
    public ?int $shopId;
    public int $barberId;
    public int $customerId;
    public int $serviceId;

    public string $serviceLocation;

    public string $serviceDate;
    public string $completedAt;
    public string $createdAt;

    public function __construct(
        ?int $id = null,
        int $appointmentId = 0,
        ?int $shopId = null,
        int $barberId = 0,
        int $customerId = 0,
        int $serviceId = 0,
        string $serviceLocation = '',
        string $serviceDate = '',
        string $completedAt = '',
        string $createdAt = ''
    ) {
        $this->id = $id;
        $this->appointmentId = $appointmentId;
        $this->shopId = $shopId;
        $this->barberId = $barberId;
        $this->customerId = $customerId;
        $this->serviceId = $serviceId;
        $this->serviceLocation = $serviceLocation;
        $this->serviceDate = $serviceDate;
        $this->completedAt = $completedAt;
        $this->createdAt = $createdAt;
    }
}