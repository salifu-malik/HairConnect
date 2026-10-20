<?php

require_once __DIR__ . '/bootstrap.php';

use App\Repositories\AppointmentRepository;

$repository = new AppointmentRepository();

$appointments = $repository->findByBarber(1);

echo "Appointments for barber ID 1:\n\n";

foreach ($appointments as $appointment) {
    echo sprintf(
        "ID: %s | Location: %s | Service: %s | Date: %s | Time: %s | Status: %s\n",
        $appointment['id'],
        $appointment['service_location'],
        $appointment['service_name'] ?? 'Unknown service',
        $appointment['appointment_date'],
        $appointment['appointment_time'],
        $appointment['status']
    );
}