<?php

namespace App\Repositories;

use App\Helpers\DatabaseManager;
use PDO;

class CustomerBookingRestrictionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseManager::getConnection('booking_db');
    }

    /**
     * Check whether a customer is restricted from booking today.
     *
     * The restriction includes both its start and end dates.
     */
    public function findActiveRestriction(int $customerId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                customer_id,
                restriction_start_date,
                restriction_end_date,
                reason
            FROM customer_booking_restrictions
            WHERE customer_id = :customer_id
              AND restriction_start_date <= :today
              AND restriction_end_date >= :today
            ORDER BY restriction_end_date DESC, id DESC
            LIMIT 1
        ");

        $today = (new \DateTimeImmutable(
            'now',
            new \DateTimeZone('Africa/Accra')
        ))->format('Y-m-d');

        $stmt->execute([
            'customer_id' => $customerId,
            'today' => $today,
        ]);

        $restriction = $stmt->fetch(PDO::FETCH_ASSOC);

        return $restriction ?: null;
    }

    /**
     * Record a cancellation restriction.
     *
     * Cancellation date and the following three calendar days
     * are restricted.
     */
    public function createCancellationRestriction(
        int $customerId,
        string $cancellationDate
    ): bool {
        $startDate = new \DateTimeImmutable(
            $cancellationDate,
            new \DateTimeZone('Africa/Accra')
        );

        $endDate = $startDate->modify('+3 days');

        $stmt = $this->db->prepare("
            INSERT INTO customer_booking_restrictions (
                customer_id,
                restriction_start_date,
                restriction_end_date,
                reason
            ) VALUES (
                :customer_id,
                :restriction_start_date,
                :restriction_end_date,
                :reason
            )
        ");

        return $stmt->execute([
            'customer_id' => $customerId,
            'restriction_start_date' => $startDate->format('Y-m-d'),
            'restriction_end_date' => $endDate->format('Y-m-d'),
            'reason' => 'Appointment cancellation',
        ]);
    }
}