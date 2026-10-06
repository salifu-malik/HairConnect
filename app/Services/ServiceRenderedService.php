<?php

namespace App\Services;

use App\Models\ServiceRendered;
use App\Repositories\ServiceRenderedRepository;
use App\Repositories\AppointmentRepository;
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
     * This method should only be called when an appointment
     * has legitimately reached the completed state.
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
         *
         * We expect the existing AppointmentRepository to provide
         * a method for retrieving an appointment by ID.
         */
        $appointment =
            $this->appointmentRepository
                ->findById($appointmentId);

        if (!$appointment) {
            throw new Exception('Appointment not found.');
        }

        /*
         * Only completed appointments can become
         * service-rendered records.
         */
        if ($appointment->status !== 'completed') {
            throw new Exception(
                'Only completed appointments can be recorded as services rendered.'
            );
        }

        /*
         * Validate the actual servicing barber.
         *
         * This is deliberately separate from the original
         * appointment barber because reassignment may occur.
         */
        if ($actualBarberId <= 0) {
            throw new Exception('Invalid servicing barber.');
        }

        /*
         * A SHOP appointment should retain its shop.
         * A HOME appointment does not belong to a shop
         * for service-rendering purposes unless your business
         * rules explicitly associate it with one.
         */
        $shopId = null;

        if ($appointment->serviceLocation === 'SHOP') {
            $shopId = $appointment->shopId;

            if ($shopId === null) {
                throw new Exception(
                    'Shop appointment is missing a shop.'
                );
            }
        }

        /*
         * Build the completed-service record.
         */
        $serviceRendered = new ServiceRendered(
            appointmentId: $appointment->id,
            shopId: $shopId,
            barberId: $actualBarberId,
            customerId: $appointment->customerId,
            serviceId: $appointment->serviceId,
            serviceLocation: $appointment->serviceLocation,
            serviceDate: $appointment->appointmentDate
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
        $this->validateDateRange($fromDate, $toDate);

        $serviceLocation =
            $this->normalizeServiceLocation($serviceLocation);

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

        $this->validateDateRange($fromDate, $toDate);

        $serviceLocation =
            $this->normalizeServiceLocation($serviceLocation);

        if ($barberId !== null && $barberId <= 0) {
            throw new Exception('Invalid barber.');
        }

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
     * Get barber service count.
     */
    public function countBarberServices(
        int $barberId,
        string $fromDate,
        string $toDate,
        ?string $serviceLocation = null
    ): int {
        $this->validateDateRange($fromDate, $toDate);

        $serviceLocation =
            $this->normalizeServiceLocation($serviceLocation);

        return $this->serviceRenderedRepository
            ->countByBarberAndDateRange(
                $barberId,
                $fromDate,
                $toDate,
                $serviceLocation
            );
    }

    /**
     * Get shop service count.
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

        $this->validateDateRange($fromDate, $toDate);

        $serviceLocation =
            $this->normalizeServiceLocation($serviceLocation);

        if ($barberId !== null && $barberId <= 0) {
            throw new Exception('Invalid barber.');
        }

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
     * Returns null when no location filter was supplied.
     */
    private function normalizeServiceLocation(
        ?string $serviceLocation
    ): ?string {
        if (
            $serviceLocation === null ||
            trim($serviceLocation) === '' ||
            strtoupper(trim($serviceLocation)) === 'ALL'
        ) {
            return null;
        }

        $serviceLocation =
            strtoupper(trim($serviceLocation));

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

        if (!$from || $from->format('Y-m-d') !== $fromDate) {
            throw new Exception(
                'Invalid start date.'
            );
        }

        if (!$to || $to->format('Y-m-d') !== $toDate) {
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