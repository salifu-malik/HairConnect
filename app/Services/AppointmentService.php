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
     * Check in a shop appointment.
     *
     * Only the owner of the appointment's shop
     * can check the customer in.
     */
    public function checkInAppointment(
        int $appointmentId,
        int $userId
    ): void {
        $appointment = $this->getAppointment($appointmentId);

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

        if ($appointment['status'] !== 'confirmed') {
            throw new Exception(
                'Only confirmed appointments can be checked in.'
            );
        }

        $updated = $this->appointmentRepository
            ->checkInAppointment(
                $appointmentId,
                $userId
            );

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
    public function startAppointment(
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
                'You are not authorized to start this appointment.'
            );
        }

        if ($appointment['status'] !== 'checked_in') {
            throw new Exception(
                'Only checked-in appointments can be started.'
            );
        }

        $updated = $this->appointmentRepository
            ->startAppointment($appointmentId);

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
}