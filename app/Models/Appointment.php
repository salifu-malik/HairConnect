<?php

namespace App\Models;

class Appointment {
    public int $id;
    public int $customerId;
    public int $shopId;
    public int $barberId;
    public int $serviceId;
    public string $appointmentDate;
    public string $appointmentTime;
    public string $status;

    public function __construct(array $data) {
        $this->id = $data['id'] ?? 0;
        $this->customerId = $data['customer_id'] ?? 0;
        $this->shopId = $data['shop_id'] ?? 0;
        $this->barberId = $data['barber_id'] ?? 0;
        $this->serviceId = $data['service_id'] ?? 0;
        $this->appointmentDate = $data['appointment_date'] ?? '';
        $this->appointmentTime = $data['appointment_time'] ?? '';
        $this->status = $data['status'] ?? 'pending';
    }
}
