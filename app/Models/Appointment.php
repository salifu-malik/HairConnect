<?php

namespace App\Models;

class Appointment
{
    public int $id;
    public int $customerId;
    public ?int $shopId;
    public int $barberId;
    public int $serviceId;
    public string $serviceLocation;
    public int $durationMinutes;
    public string $appointmentDate;
    public string $appointmentTime;
    public string $status;

    public function __construct(array $data)
    {
        $this->id = (int) ($data['id'] ?? 0);

        $this->customerId = (int) ($data['customer_id'] ?? 0);

        $this->shopId = isset($data['shop_id'])
            ? (int) $data['shop_id']
            : null;

        $this->barberId = (int) ($data['barber_id'] ?? 0);

        $this->serviceId = (int) ($data['service_id'] ?? 0);

        $this->serviceLocation =
            $data['service_location'] ?? 'SHOP';

        $this->durationMinutes =
            (int) ($data['duration_minutes'] ?? 0);

        $this->appointmentDate =
            $data['appointment_date'] ?? '';

        $this->appointmentTime =
            $data['appointment_time'] ?? '';

        $this->status =
            $data['status'] ?? 'pending';
    }
}
