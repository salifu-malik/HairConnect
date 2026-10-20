<?php

require_once __DIR__ . '/bootstrap.php';

use App\Repositories\AppointmentRepository;

try {
    $repository = new AppointmentRepository();

    $appointments = $repository->findByCustomer(8);

    echo "Appointments returned: " . count($appointments) . PHP_EOL;

    foreach ($appointments as $appointment) {
        echo json_encode([
                'id' => $appointment['id'],
                'service_location' => $appointment['service_location'],
                'status' => $appointment['status'],
                'booking_code' => $appointment['booking_code'],
                'service_name' => $appointment['service_name'],
                'barber_name' => $appointment['barber_name'],
            ], JSON_PRETTY_PRINT) . PHP_EOL;
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}