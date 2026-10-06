<?php

namespace App\Repositories;

use App\Models\ServiceRendered;
use PDO;
use PDOException;

class ServiceRenderedRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Create a completed-service record.
     *
     * Returns the newly created record ID.
     */
    public function create(ServiceRendered $serviceRendered): int
    {
        $sql = "
            INSERT INTO service_rendered (
                appointment_id,
                shop_id,
                barber_id,
                customer_id,
                service_id,
                service_location,
                service_date
            )
            VALUES (
                :appointment_id,
                :shop_id,
                :barber_id,
                :customer_id,
                :service_id,
                :service_location,
                :service_date
            )
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'appointment_id'  => $serviceRendered->appointmentId,
            'shop_id'         => $serviceRendered->shopId,
            'barber_id'       => $serviceRendered->barberId,
            'customer_id'     => $serviceRendered->customerId,
            'service_id'      => $serviceRendered->serviceId,
            'service_location'=> $serviceRendered->serviceLocation,
            'service_date'    => $serviceRendered->serviceDate,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Find a completed-service record by appointment ID.
     */
    public function findByAppointmentId(
        int $appointmentId
    ): ?ServiceRendered {
        $stmt = $this->db->prepare("
            SELECT
                id,
                appointment_id,
                shop_id,
                barber_id,
                customer_id,
                service_id,
                service_location,
                service_date,
                completed_at,
                created_at
            FROM service_rendered
            WHERE appointment_id = :appointment_id
            LIMIT 1
        ");

        $stmt->execute([
            'appointment_id' => $appointmentId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapRowToModel($row);
    }

    /**
     * Check whether an appointment has already
     * been recorded as a completed service.
     */
    public function existsByAppointmentId(
        int $appointmentId
    ): bool {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM service_rendered
            WHERE appointment_id = :appointment_id
            LIMIT 1
        ");

        $stmt->execute([
            'appointment_id' => $appointmentId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Get completed services for a barber.
     */
    public function findByBarberAndDateRange(
        int $barberId,
        string $fromDate,
        string $toDate,
        ?string $serviceLocation = null
    ): array {
        $sql = "
            SELECT
                id,
                appointment_id,
                shop_id,
                barber_id,
                customer_id,
                service_id,
                service_location,
                service_date,
                completed_at,
                created_at
            FROM service_rendered
            WHERE barber_id = :barber_id
              AND service_date BETWEEN :from_date AND :to_date
        ";

        $params = [
            'barber_id' => $barberId,
            'from_date' => $fromDate,
            'to_date'   => $toDate,
        ];

        if ($serviceLocation !== null) {
            $sql .= "
                AND service_location = :service_location
            ";

            $params['service_location'] = $serviceLocation;
        }

        $sql .= "
            ORDER BY service_date DESC, completed_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(
            fn(array $row) => $this->mapRowToModel($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * Get completed services for a shop.
     */
    public function findByShopAndDateRange(
        int $shopId,
        string $fromDate,
        string $toDate,
        ?int $barberId = null,
        ?string $serviceLocation = null
    ): array {
        $sql = "
            SELECT
                id,
                appointment_id,
                shop_id,
                barber_id,
                customer_id,
                service_id,
                service_location,
                service_date,
                completed_at,
                created_at
            FROM service_rendered
            WHERE shop_id = :shop_id
              AND service_date BETWEEN :from_date AND :to_date
        ";

        $params = [
            'shop_id'  => $shopId,
            'from_date'=> $fromDate,
            'to_date'  => $toDate,
        ];

        if ($barberId !== null) {
            $sql .= "
                AND barber_id = :barber_id
            ";

            $params['barber_id'] = $barberId;
        }

        if ($serviceLocation !== null) {
            $sql .= "
                AND service_location = :service_location
            ";

            $params['service_location'] = $serviceLocation;
        }

        $sql .= "
            ORDER BY service_date DESC, completed_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(
            fn(array $row) => $this->mapRowToModel($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * Count completed services for a barber.
     */
    public function countByBarberAndDateRange(
        int $barberId,
        string $fromDate,
        string $toDate,
        ?string $serviceLocation = null
    ): int {
        $sql = "
            SELECT COUNT(*)
            FROM service_rendered
            WHERE barber_id = :barber_id
              AND service_date BETWEEN :from_date AND :to_date
        ";

        $params = [
            'barber_id' => $barberId,
            'from_date' => $fromDate,
            'to_date'   => $toDate,
        ];

        if ($serviceLocation !== null) {
            $sql .= "
                AND service_location = :service_location
            ";

            $params['service_location'] = $serviceLocation;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Count completed services for a shop.
     */
    public function countByShopAndDateRange(
        int $shopId,
        string $fromDate,
        string $toDate,
        ?int $barberId = null,
        ?string $serviceLocation = null
    ): int {
        $sql = "
            SELECT COUNT(*)
            FROM service_rendered
            WHERE shop_id = :shop_id
              AND service_date BETWEEN :from_date AND :to_date
        ";

        $params = [
            'shop_id'  => $shopId,
            'from_date'=> $fromDate,
            'to_date'  => $toDate,
        ];

        if ($barberId !== null) {
            $sql .= "
                AND barber_id = :barber_id
            ";

            $params['barber_id'] = $barberId;
        }

        if ($serviceLocation !== null) {
            $sql .= "
                AND service_location = :service_location
            ";

            $params['service_location'] = $serviceLocation;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Map database row to ServiceRendered model.
     */
    private function mapRowToModel(array $row): ServiceRendered
    {
        return new ServiceRendered(
            id: isset($row['id']) ? (int) $row['id'] : null,
            appointmentId: (int) $row['appointment_id'],
            shopId: isset($row['shop_id'])
                ? (int) $row['shop_id']
                : null,
            barberId: (int) $row['barber_id'],
            customerId: (int) $row['customer_id'],
            serviceId: (int) $row['service_id'],
            serviceLocation: $row['service_location'],
            serviceDate: $row['service_date'],
            completedAt: $row['completed_at'],
            createdAt: $row['created_at']
        );
    }
}