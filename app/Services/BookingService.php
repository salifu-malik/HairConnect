<?php

namespace App\Services;

use App\Repositories\ShopRepository;
use App\Repositories\BarberRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\AppointmentRepository;
use App\Repositories\BarberScheduleRepository;
use Exception;
use DateTime;

class BookingService
{
    private ShopRepository $shopRepository;
    private BarberRepository $barberRepository;
    private ServiceRepository $serviceRepository;
    private AppointmentRepository $appointmentRepository;
    private BarberScheduleRepository $barberScheduleRepository;

    public function __construct(
        ShopRepository $shopRepository,
        BarberRepository $barberRepository,
        ServiceRepository $serviceRepository,
        AppointmentRepository $appointmentRepository,
        BarberScheduleRepository $barberScheduleRepository
    ) {
        $this->shopRepository = $shopRepository;
        $this->barberRepository = $barberRepository;
        $this->serviceRepository = $serviceRepository;
        $this->appointmentRepository = $appointmentRepository;
        $this->barberScheduleRepository = $barberScheduleRepository;
    }

    public function createAppointment(
        array $data,
        int $customerId
    ) {
        /*
         * ---------------------------------------------------------
         * 1. Validate required fields
         * ---------------------------------------------------------
         */

        if (
            !isset($data['barberId']) ||
            !isset($data['serviceId']) ||
            !isset($data['date']) ||
            !isset($data['time'])
        ) {
            throw new Exception(
                'Barber, service, date and time are required.'
            );
        }

        if ($customerId <= 0) {
            throw new Exception('Invalid customer.');
        }

        $barberId = (int) $data['barberId'];
        $serviceId = (int) $data['serviceId'];
        $date = trim((string) $data['date']);
        $time = trim((string) $data['time']);

        if ($barberId <= 0) {
            throw new Exception('Invalid barber.');
        }

        if ($serviceId <= 0) {
            throw new Exception('Invalid service.');
        }

        /*
         * ---------------------------------------------------------
         * 2. Validate date
         * ---------------------------------------------------------
         */

        $dateObject = DateTime::createFromFormat('Y-m-d', $date);

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $date
        ) {
            throw new Exception('Invalid appointment date.');
        }

        $today = new DateTime('today');

        if ($dateObject < $today) {
            throw new Exception(
                'You cannot book an appointment for a past date.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 3. Validate time
         * ---------------------------------------------------------
         */

        $timeObject = DateTime::createFromFormat('H:i', $time);

        if (
            !$timeObject ||
            $timeObject->format('H:i') !== $time
        ) {
            throw new Exception('Invalid appointment time.');
        }

        /*
         * ---------------------------------------------------------
         * 4. Find barber
         * ---------------------------------------------------------
         */

        $barber = $this->barberRepository->findById($barberId);

        if (!$barber) {
            throw new Exception('Barber not found.');
        }

        /*
         * The barber must have:
         * - admin/shop approval
         * - active status
         */

        if ($barber->approvalStatus !== 'approved') {
            throw new Exception(
                'This barber has not been approved yet.'
            );
        }

        if ($barber->status !== 'active') {
            throw new Exception(
                'This barber is currently unavailable.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 5. Find barber's shop
         * ---------------------------------------------------------
         */

        $shop = $this->shopRepository->findById(
            $barber->shopId
        );

        if (!$shop) {
            throw new Exception(
                'The barber is not attached to a valid shop.'
            );
        }

        /*
         * The shop must also be approved and active.
         */

        if ($shop->approvalStatus !== 'approved') {
            throw new Exception(
                'This shop has not been approved yet.'
            );
        }

        if ($shop->status !== 'active') {
            throw new Exception(
                'This shop is currently unavailable.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 6. Find service
         * ---------------------------------------------------------
         */

        $service = $this->serviceRepository->findById(
            $serviceId
        );

        if (!$service) {
            throw new Exception('Service not found.');
        }

        if ($service->status !== 'active') {
            throw new Exception(
                'This service is currently unavailable.'
            );
        }

        /*
         * The service must belong to the same shop
         * as the selected barber.
         */

        if ((int) $service->shopId !== (int) $shop->id) {
            throw new Exception(
                'This service does not belong to the barber\'s shop.'
            );
        }

        /*
         * If the service belongs specifically to a barber,
         * make sure the selected barber is that barber.
         */

        if (
            $service->barberId !== null &&
            (int) $service->barberId !== $barberId
        ) {
            throw new Exception(
                'This service is not available from the selected barber.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 7. Find barber's working schedule
         * ---------------------------------------------------------
         */

        $day = $dateObject->format('l');

        $schedule = $this->barberScheduleRepository
            ->findByBarberAndDay(
                $barberId,
                $day
            );

        if (!$schedule) {
            throw new Exception(
                "The barber does not work on {$day}s."
            );
        }

        /*
         * ---------------------------------------------------------
         * 8. Calculate appointment start and end
         * ---------------------------------------------------------
         */

        $appointmentStart = new DateTime(
            $date . ' ' . $time . ':00'
        );

        $appointmentEnd = clone $appointmentStart;

        $appointmentEnd->modify(
            '+' . (int) $service->durationMinutes . ' minutes'
        );

        /*
         * Convert the barber's schedule into DateTime objects
         * for this particular appointment date.
         */

        $scheduleStart = new DateTime(
            $date . ' ' . $schedule['start_time']
        );

        $scheduleEnd = new DateTime(
            $date . ' ' . $schedule['end_time']
        );

        /*
         * ---------------------------------------------------------
         * 9. Check whether appointment is inside working hours
         * ---------------------------------------------------------
         */

        if ($appointmentStart < $scheduleStart) {
            throw new Exception(
                'The selected time is before the barber\'s working hours.'
            );
        }

        if ($appointmentEnd > $scheduleEnd) {
            throw new Exception(
                'The appointment extends beyond the barber\'s working hours.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 10. Prepare appointment data
         * ---------------------------------------------------------
         */

        $appointmentData = [
            'customer_id' => $customerId,
            'shop_id' => $shop->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'appointment_date' => $date,
            'appointment_time' => $time,
        ];

        /*
         * ---------------------------------------------------------
         * 11. Create appointment
         * ---------------------------------------------------------
         */

        return $this->appointmentRepository->create(
            $appointmentData
        );
    }
}