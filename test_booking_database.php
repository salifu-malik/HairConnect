<?php

require_once __DIR__ . '/bootstrap.php';

use App\Helpers\DatabaseManager;

try {
    $db = DatabaseManager::getConnection('booking_db');

    echo "Connected database: "
        . $db->query("SELECT DATABASE()")->fetchColumn()
        . PHP_EOL;

    $stmt = $db->prepare("
        SELECT id, customer_id, service_location, booking_code
        FROM appointments
        WHERE customer_id = :customer_id
        ORDER BY id DESC
    ");

    $stmt->execute(['customer_id' => 8]);

    print_r($stmt->fetchAll());

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}