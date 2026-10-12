<?php

namespace App\Services;

use App\Repositories\AppointmentRepository;
use App\Repositories\BarberRepository;
use App\Repositories\ShopRepository;
use Exception;

class AppointmentService
{
    private AppointmentRepository $appointmentRepository;
    private BarberRepository $barberRepository;
    private ShopRepository $shopRepository;

    public function __construct(
        AppointmentRepository $appointmentRepository,
        BarberRepository $barberRepository,
        ShopRepository $shopRepository
    ) {
        $this->appointmentRepository = $appointmentRepository;
        $this->barberRepository = $barberRepository;
        $this->shopRepository = $shopRepository;
    }

    /**
     * Confirm a pending appointment.
     *
     * Only the barber assigned to the appointment
     * can confirm it.
     */
    public function confirmAppointment(
        int $appointmentId,
        int $userId
    ): void {
        $appointment = $this->getAppointment($appointmentId);

        $barber = $this->barberRepository->findByUserId($userId);

        if (!$barber) {
            throw new Exception('Barber profile not found.');
        }

        if ((int) $appointment['barber_id'] !== (int) $barber->id) {
            throw new Exception(
                'You are not authorized to confirm this appointment.'
            );
        }

        if ($appointment['status'] !== 'pending') {
            throw new Exception(
                'Only pending appointments can be confirmed.'
            );
        }

        $updated = $this->appointmentRepository
            ->confirmAppointment($appointmentId);

        if (!$updated) {
            throw new Exception(
                'Unable to confirm appointment.'
            );
        }
    }

    /**
     * Check in a confirmed shop appointment.
     *
     * Only the owner of the appointment's shop can check
     * the customer in, using the correct booking code.
     */
    public function checkInAppointment(
        int $appointmentId,
        int $userId,
        string $bookingCode
    ): void {
        $appointment = $this->getAppointment($appointmentId);

        // Only SHOP appointments can be checked in.
        if ($appointment['service_location'] !== 'SHOP') {
            throw new Exception(
                'Only shop appointments can be checked in.'
            );
        }

        if (empty($appointment['shop_id'])) {
            throw new Exception(
                'This appointment is not associated with a shop.'
            );
        }

        // Confirm that the authenticated user owns the shop.
        $shops = $this->shopRepository->findByOwnerId($userId);

        $ownsShop = false;

        foreach ($shops as $shop) {
            if ((int) $shop->id === (int) $appointment['shop_id']) {
                $ownsShop = true;
                break;
            }
        }

        if (!$ownsShop) {
            throw new Exception(
                'You are not authorized to check in this appointment.'
            );
        }

        // The appointment must be confirmed.
        if ($appointment['status'] !== 'confirmed') {
            throw new Exception(
                'Only confirmed appointments can be checked in.'
            );
        }

        // Validate the booking code.
        $storedCode = (string) ($appointment['booking_code'] ?? '');

        if (
            $storedCode === ''
            || !preg_match('/^\d{5}$/D', $storedCode)
            || !hash_equals($storedCode, $bookingCode)
        ) {
            throw new Exception('Invalid booking code.');
        }

        // Check-in is not permitted before the appointment date.
        $today = new \DateTimeImmutable(
            'today',
            new \DateTimeZone('Africa/Accra')
        );

        $appointmentDate = new \DateTimeImmutable(
            $appointment['appointment_date'],
            new \DateTimeZone('Africa/Accra')
        );

        if ($appointmentDate > $today) {
            throw new Exception(
                'Check-in is only allowed on the appointment date.'
            );
        }

        $updated = $this->appointmentRepository
            ->checkInAppointment($appointmentId, $userId);

        if (!$updated) {
            throw new Exception(
                'Unable to check in appointment.'
            );
        }
    }

    /**
     * Start a checked-in appointment.
     *
     * Only the barber assigned to the appointment
     * can start it.
     */
    public function startAppointment(int $appointmentId, int $userId): void
    {
        $appointment = $this->getAppointment($appointmentId);

        $barber = $this->barberRepository->findByUserId($userId);

        if (!$barber) {
            throw new Exception('Barber profile not found.');
        }

        if ((int)$appointment['barber_id'] !== (int)$barber->id) {
            throw new Exception(
                'You are not authorized to start this appointment.'
            );
        }

        $this->assertScheduledStartTimeReached($appointment);

        $serviceLocation = strtoupper($appointment['service_location']);
        $status = strtolower($appointment['status']);

        if ($serviceLocation === 'SHOP') {
            if ($status === 'in_progress') {
                throw new Exception(
                    'Appointment has already been started.'
                );
            }

            if ($status === 'completed') {
                throw new Exception(
                    'Completed appointments cannot be started again.'
                );
            }

            if ($status !== 'checked_in') {
                throw new Exception(
                    'Shop appointments must be checked in before they can be started.'
                );
            }
        } elseif ($serviceLocation === 'HOME') {
            if ($status === 'in_progress') {
                throw new Exception(
                    'Appointment has already been started.'
                );
            }

            if ($status === 'completed') {
                throw new Exception(
                    'Completed appointments cannot be started again.'
                );
            }

            if ($status !== 'confirmed') {
                throw new Exception(
                    'Only confirmed home appointments can be started.'
                );
            }
        } else {
            throw new Exception('Invalid appointment service location.');
        }

        $updated = $this->appointmentRepository->startAppointment(
            $appointmentId
        );

        if (!$updated) {
            throw new Exception(
                'Unable to start appointment.'
            );
        }
    }



    /**
     * Complete an appointment.
     *
     * Only the barber assigned to the appointment
     * can complete it.
     */
    public function completeAppointment(
        int $appointmentId,
        int $userId
    ): void {
        $appointment = $this->getAppointment($appointmentId);

        $barber = $this->barberRepository->findByUserId($userId);

        if (!$barber) {
            throw new Exception('Barber profile not found.');
        }

        if ((int) $appointment['barber_id'] !== (int) $barber->id) {
            throw new Exception(
                'You are not authorized to complete this appointment.'
            );
        }

        if ($appointment['status'] !== 'in_progress') {
            throw new Exception(
                'Only appointments in progress can be completed.'
            );
        }

        $updated = $this->appointmentRepository
            ->completeAppointment($appointmentId);

        if (!$updated) {
            throw new Exception(
                'Unable to complete appointment.'
            );
        }
    }

    /**
     * Retrieve an appointment or throw an exception.
     */
    private function getAppointment(int $appointmentId): array
    {
        if ($appointmentId <= 0) {
            throw new Exception(
                'Invalid appointment ID.'
            );
        }

        $appointment = $this->appointmentRepository
            ->findById($appointmentId);

        if (!$appointment) {
            throw new Exception(
                'Appointment not found.'
            );
        }

        return $appointment;
    }



    public function cancelAppointment(int $appointmentId, int $userId): void
    {
        $appointment = $this->getAppointment($appointmentId);

        if ((int)$appointment['customer_id'] !== $userId) {
            throw new Exception(
                'You are not authorized to cancel this appointment.'
            );
        }

        if (!in_array($appointment['status'], ['pending', 'confirmed'], true)) {
            throw new Exception(
                'Only pending or confirmed appointments can be cancelled.'
            );
        }

        $updated = $this->appointmentRepository->cancelAppointment($appointmentId);

        if (!$updated) {
            throw new Exception('Unable to cancel appointment.');
        }
    }



    /**
     * Prevent an appointment from starting before its
     * scheduled date and time.
     */
    private function assertScheduledStartTimeReached(
        array $appointment
    ): void {
        $timezone = new \DateTimeZone('Africa/Accra');

        $scheduledStart = new \DateTimeImmutable(
            $appointment['appointment_date']
            . ' '
            . $appointment['appointment_time'],
            $timezone
        );

        $now = new \DateTimeImmutable('now', $timezone);

        if ($now < $scheduledStart) {
            throw new Exception(
                'The appointment cannot start before its scheduled date and time.'
            );
        }
    }



}