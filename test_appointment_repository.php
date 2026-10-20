<?php

require_once __DIR__ . '/bootstrap.php';

use App\Repositories\AppointmentRepository;

$repository = new AppointmentRepository();

$appointments = $repository->findByCustomer(8);

echo "Appointments for customer 8:\n\n";

foreach ($appointments as $appointment) {
    print_r($appointment);
    echo "\n-----------------------------\n";
}