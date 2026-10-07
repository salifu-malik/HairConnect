<?php

namespace App\Services;

use App\Models\ServiceRendered;
use App\Repositories\AppointmentRepository;
use App\Repositories\ServiceRenderedRepository;
use Exception;

class ServiceRenderedService
{
    private ServiceRenderedRepository $serviceRenderedRepository;
    private AppointmentRepository $appointmentRepository;

    public function __construct(
        ServiceRenderedRepository $serviceRenderedRepository,
        AppointmentRepository $appointmentRepository
    ) {
        $this->serviceRenderedRepository = $serviceRenderedRepository;
        $this->appointmentRepository = $appointmentRepository;
    }

    /**
     * Record a completed service from an appointment.
     *
     * The appointment must already be completed.
     *
     * $actualBarberId represents the barber who actually
     * performed the service.
     */
    public function recordCompletedService(
        int $appointmentId,
        int $actualBarberId
    ): int {
        if ($appointmentId <= 0) {
            throw new Exception('Invalid appointment.');
        }

        if ($actualBarberId <= 0) {
            throw new Exception('Invalid barber.');
        }

        /*
         * Prevent duplicate service-rendered records.
         */
        if (
            $this->serviceRenderedRepository
                ->existsByAppointmentId($appointmentId)
        ) {
            throw new Exception(
                'This appointment has already been recorded as a completed service.'
            );
        }

        /*
         * Retrieve the appointment.
         */
        $appointment = $this->appointmentRepository
            ->findById($appointmentId);

        if (!$appointment) {
            throw new Exception('Appointment not found.');
        }

        /*
         * Only completed appointments can become
         * service-rendered records.
         */
        if ($appointment['status'] !== 'completed') {
            throw new Exception(
                'Only completed appointments can be recorded as services rendered.'
            );
        }

        /*
         * Validate service location.
         */
        $serviceLocation = strtoupper(
            trim($appointment['service_location'])
        );

        if (!in_array(
            $serviceLocation,
            ['SHOP', 'HOME'],
            true
        )) {
            throw new Exception(
                'Invalid appointment service location.'
            );
        }

        /*
         * SHOP services must have a shop.
         *
         * HOME services may have no shop.
         */
        $shopId = null;

        if ($serviceLocation === 'SHOP') {
            if (
                !isset($appointment['shop_id']) ||
                (int) $appointment['shop_id'] <= 0
            ) {
                throw new Exception(
                    'Shop appointment is missing a shop.'
                );
            }

            $shopId = (int) $appointment['shop_id'];
        }

        /*
         * Create the completed-service record.
         *
         * IMPORTANT:
         * actualBarberId is used here instead of
         * appointment['barber_id'].
         *
         * This allows future barber reassignment.
         */
        $serviceRendered = new ServiceRendered(
            appointmentId: (int) $appointment['id'],
            shopId: $shopId,
            barberId: $actualBarberId,
            customerId: (int) $appointment['customer_id'],
            serviceId: (int) $appointment['service_id'],
            serviceLocation: $serviceLocation,
            serviceDate: $appointment['appointment_date']
        );

        return $this->serviceRenderedRepository
            ->create($serviceRendered);
    }

    /**
     * Get services rendered by a barber.
     */
    public function getBarberServices(
        int $barberId,
        string $fromDate,
        string $toDate,
        ?string $serviceLocation = null
    ): array {
        if ($barberId <= 0) {
            throw new Exception('Invalid barber.');
        }

        $this->validateDateRange($fromDate, $toDate);

        $serviceLocation = $this->normalizeServiceLocation(
            $serviceLocation
        );

        return $this->serviceRenderedRepository
            ->findByBarberAndDateRange(
                $barberId,
                $fromDate,
                $toDate,
                $serviceLocation
            );
    }

    /**
     * Get services rendered within a shop.
     *
     * Optional filters:
     * - barber
     * - service location
     */
    public function getShopServices(
        int $shopId,
        string $fromDate,
        string $toDate,
        ?int $barberId = null,
        ?string $serviceLocation = null
    ): array {
        if ($shopId <= 0) {
            throw new Exception('Invalid shop.');
        }

        if ($barberId !== null && $barberId <= 0) {
            throw new Exception('Invalid barber.');
        }

        $this->validateDateRange($fromDate, $toDate);

        $serviceLocation = $this->normalizeServiceLocation(
            $serviceLocation
        );

        return $this->serviceRenderedRepository
            ->findByShopAndDateRange(
                $shopId,
                $fromDate,
                $toDate,
                $barberId,
                $serviceLocation
            );
    }

    /**
     * Count services rendered by a barber.
     */
    public function countBarberServices(
        int $barberId,
        string $fromDate,
        string $toDate,
        ?string $serviceLocation = null
    ): int {
        if ($barberId <= 0) {
            throw new Exception('Invalid barber.');
        }

        $this->validateDateRange($fromDate, $toDate);

        $serviceLocation = $this->normalizeServiceLocation(
            $serviceLocation
        );

        return $this->serviceRenderedRepository
            ->countByBarberAndDateRange(
                $barberId,
                $fromDate,
                $toDate,
                $serviceLocation
            );
    }

    /**
     * Count services rendered within a shop.
     */
    public function countShopServices(
        int $shopId,
        string $fromDate,
        string $toDate,
        ?int $barberId = null,
        ?string $serviceLocation = null
    ): int {
        if ($shopId <= 0) {
            throw new Exception('Invalid shop.');
        }

        if ($barberId !== null && $barberId <= 0) {
            throw new Exception('Invalid barber.');
        }

        $this->validateDateRange($fromDate, $toDate);

        $serviceLocation = $this->normalizeServiceLocation(
            $serviceLocation
        );

        return $this->serviceRenderedRepository
            ->countByShopAndDateRange(
                $shopId,
                $fromDate,
                $toDate,
                $barberId,
                $serviceLocation
            );
    }

    /**
     * Normalize service location.
     *
     * ALL or empty/null means no location filter.
     */
    private function normalizeServiceLocation(
        ?string $serviceLocation
    ): ?string {
        if (
            $serviceLocation === null ||
            trim($serviceLocation) === ''
        ) {
            return null;
        }

        $serviceLocation = strtoupper(
            trim($serviceLocation)
        );

        if ($serviceLocation === 'ALL') {
            return null;
        }

        if (!in_array(
            $serviceLocation,
            ['SHOP', 'HOME'],
            true
        )) {
            throw new Exception(
                'Invalid service location.'
            );
        }

        return $serviceLocation;
    }

    /**
     * Validate reporting date range.
     */
    private function validateDateRange(
        string $fromDate,
        string $toDate
    ): void {
        $from = \DateTime::createFromFormat(
            'Y-m-d',
            $fromDate
        );

        $to = \DateTime::createFromFormat(
            'Y-m-d',
            $toDate
        );

        if (
            !$from ||
            $from->format('Y-m-d') !== $fromDate
        ) {
            throw new Exception(
                'Invalid start date.'
            );
        }

        if (
            !$to ||
            $to->format('Y-m-d') !== $toDate
        ) {
            throw new Exception(
                'Invalid end date.'
            );
        }

        if ($from > $to) {
            throw new Exception(
                'The start date cannot be after the end date.'
            );
        }
    }
}