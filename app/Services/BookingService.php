<?php

namespace App\Services;

use App\Repositories\ShopRepository;
use App\Repositories\BarberRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\BarberHomeServiceRepository;
use App\Repositories\AppointmentRepository;
use App\Repositories\BarberScheduleRepository;
use Exception;
use DateTime;

class BookingService
{
    private ShopRepository $shopRepository;
    private BarberRepository $barberRepository;
    private ServiceRepository $serviceRepository;
    private BarberHomeServiceRepository $barberHomeServiceRepository;
    private AppointmentRepository $appointmentRepository;
    private BarberScheduleRepository $barberScheduleRepository;

    public function __construct(
        ShopRepository $shopRepository,
        BarberRepository $barberRepository,
        ServiceRepository $serviceRepository,
        BarberHomeServiceRepository $barberHomeServiceRepository,
        AppointmentRepository $appointmentRepository,
        BarberScheduleRepository $barberScheduleRepository
    ) {
        $this->shopRepository = $shopRepository;
        $this->barberRepository = $barberRepository;
        $this->serviceRepository = $serviceRepository;
        $this->barberHomeServiceRepository = $barberHomeServiceRepository;
        $this->appointmentRepository = $appointmentRepository;
        $this->barberScheduleRepository = $barberScheduleRepository;
    }

    public function createAppointment(
        array $data,
        int $customerId
    ) {

         //Validate required fields
        if (
            !isset($data['barberId']) ||
            !isset($data['serviceId']) ||
            !isset($data['serviceLocation']) ||
            !isset($data['date']) ||
            !isset($data['time'])
        ) {
            throw new Exception(
                'Barber, service, service location, date and time are required.'
            );
        }

        if ($customerId <= 0) {
            throw new Exception('Invalid customer.');
        }

        $barberId = (int) $data['barberId'];
        $serviceId = (int) $data['serviceId'];

        $serviceLocation = strtoupper(
            trim((string) $data['serviceLocation'])
        );

        $date = trim((string) $data['date']);
        $time = trim((string) $data['time']);

        if ($barberId <= 0) {
            throw new Exception('Invalid barber.');
        }

        if ($serviceId <= 0) {
            throw new Exception('Invalid service.');
        }

        if (!in_array(
            $serviceLocation,
            ['SHOP', 'HOME'],
            true
        )) {
            throw new Exception(
                'Invalid service location. Please use SHOP or HOME.'
            );
        }


         //Validate date
        $dateObject = DateTime::createFromFormat(
            'Y-m-d',
            $date
        );

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $date
        ) {
            throw new Exception(
                'Invalid appointment date.'
            );
        }

        $today = new DateTime('today');

        if ($dateObject < $today) {
            throw new Exception(
                'You cannot book an appointment for a past date.'
            );
        }


         //Validate time
        $timeObject = DateTime::createFromFormat(
            'H:i',
            $time
        );

        if (
            !$timeObject ||
            $timeObject->format('H:i') !== $time
        ) {
            throw new Exception(
                'Invalid appointment time.'
            );
        }


        //Find barber
        $barber = $this->barberRepository->findById(
            $barberId
        );

        if (!$barber) {
            throw new Exception(
                'Barber not found.'
            );
        }


         // Barber must be approved and active.
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


         /*Determine shop
        * SHOP bookings require a shop.
         *
         * HOME bookings do not require a shop because
         * independent barbers are allowed to provide
         * home services.
         */

        $shop = null;

        if ($barber->shopId !== null) {
            $shop = $this->shopRepository->findById(
                $barber->shopId
            );

            if (!$shop) {
                throw new Exception(
                    'The barber is not attached to a valid shop.'
                );
            }

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
        }


         //SHOP service is not possible for an independent barber.
        if (
            $serviceLocation === 'SHOP' &&
            $barber->shopId === null
        ) {
            throw new Exception(
                'Independent barbers cannot provide shop services.'
            );
        }


         //Find the correct service
        $durationMinutes = 0;

        if ($serviceLocation === 'SHOP') {


            //SHOP services come from the services table.
            $service = $this->serviceRepository->findById(
                $serviceId
            );

            if (!$service) {
                throw new Exception(
                    'Shop service not found.'
                );
            }

            if ($service->status !== 'active') {
                throw new Exception(
                    'This shop service is currently unavailable.'
                );
            }


            //The service must belong to the barber's shop.
            if (
                $shop === null ||
                (int) $service->shopId !== (int) $shop->id
            ) {
                throw new Exception(
                    'This service does not belong to the barber\'s shop.'
                );
            }

            /*
             * If the service belongs specifically to a barber,
             * make sure the selected barber matches.
             */

            if (
                $service->barberId !== null &&
                (int) $service->barberId !== $barberId
            ) {
                throw new Exception(
                    'This service is not available from the selected barber.'
                );
            }

            $durationMinutes = (int) $service->durationMinutes;

        } else {


            //HOME services come from barber_home_services.
            $homeService =
                $this->barberHomeServiceRepository->findById(
                    $serviceId
                );

            if (!$homeService) {
                throw new Exception(
                    'Home service not found.'
                );
            }

            if ($homeService->status !== 'active') {
                throw new Exception(
                    'This home service is currently unavailable.'
                );
            }


            //The home service must belong to the selected barber.
            if (
                (int) $homeService->barberId !==
                $barberId
            ) {
                throw new Exception(
                    'This home service is not available from the selected barber.'
                );
            }

            $durationMinutes =
                (int) $homeService->durationMinutes;
        }

        if ($durationMinutes <= 0) {
            throw new Exception(
                'Invalid service duration.'
            );
        }


        // Find barber schedule for the requested location
        $day = $dateObject->format('l');

        $schedule =
            $this->barberScheduleRepository
                ->findByBarberAndDay(
                    $barberId,
                    $day,
                    $serviceLocation
                );

        if (!$schedule) {
            throw new Exception(
                "The barber does not work on {$day}s for {$serviceLocation} services."
            );
        }

        //Calculate appointment start and end
        $appointmentStart = new DateTime(
            $date . ' ' . $time . ':00'
        );

        $appointmentEnd = clone $appointmentStart;

        $appointmentEnd->modify(
            '+' . $durationMinutes . ' minutes'
        );


         //Convert schedule to DateTime
       $scheduleStart = new DateTime(
            $date . ' ' . $schedule['start_time']
        );

        $scheduleEnd = new DateTime(
            $date . ' ' . $schedule['end_time']
        );


         //Check working hours
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


         //Check appointment conflicts
        $existingAppointments =
            $this->appointmentRepository
                ->findByBarberAndDate(
                    $barberId,
                    $date
                );

        foreach ($existingAppointments as $existing) {

            $existingStart = new DateTime(
                $date . ' ' . $existing['appointment_time']
            );

            $existingEnd = clone $existingStart;

            $existingEnd->modify(
                '+' . (int) $existing['duration_minutes'] . ' minutes'
            );

            /*
             * Two time ranges overlap when:
             *
             * new start < existing end
             * AND
             * new end > existing start
             */

            if (
                $appointmentStart < $existingEnd &&
                $appointmentEnd > $existingStart
            ) {
                throw new Exception(
                    'The barber already has an appointment during the selected time.'
                );
            }
        }


         // Prepare appointment data
        $appointmentData = [
            'customer_id' => $customerId,


             //HOME appointments do not require a shop.

            'shop_id' => $shop?->id,

            'barber_id' => $barber->id,

            'service_id' => $serviceId,

            'service_location' => $serviceLocation,

            'duration_minutes' => $durationMinutes,

            'appointment_date' => $date,

            'appointment_time' => $time,
        ];


         //Create appointment
        return $this->appointmentRepository->create(
            $appointmentData
        );
    }
}
