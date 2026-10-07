<?php

namespace App\Services;

use App\Repositories\AppointmentRepository;
use App\Repositories\BarberHomeServiceRepository;
use App\Repositories\BarberRepository;
use App\Repositories\BarberScheduleRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\ShopRepository;
use App\Exceptions\BookingConflictException;
use DateTime;
use Exception;

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

    /**
     * Create an appointment.
     *
     * This is the authoritative booking operation.
     *
     * Availability shown to the customer is only a snapshot.
     * The final conflict check happens again inside a transaction
     * after locking the barber/date resource.
     */
    public function createAppointment(
        array $data,
        int $customerId
    ): ?string {
        // ---------------------------------------------------------
        // Validate required fields
        // ---------------------------------------------------------

        if (
            !isset($data['barber_id']) ||
            !isset($data['service_id']) ||
            !isset($data['service_location']) ||
            !isset($data['appointment_date']) ||
            !isset($data['appointment_time'])
        ) {
            throw new Exception(
                'Barber, service, service location, date and time are required.'
            );
        }

        if ($customerId <= 0) {
            throw new Exception('Invalid customer.');
        }

        // ---------------------------------------------------------
        // Normalize input
        // ---------------------------------------------------------

        $barberId = (int) $data['barber_id'];
        $serviceId = (int) $data['service_id'];

        $serviceLocation = strtoupper(
            trim((string) $data['service_location'])
        );

        $date = trim((string) $data['appointment_date']);
        $time = trim((string) $data['appointment_time']);

        // ---------------------------------------------------------
        // Validate basic identifiers
        // ---------------------------------------------------------

        if ($barberId <= 0) {
            throw new Exception('Invalid barber.');
        }

        if ($serviceId <= 0) {
            throw new Exception('Invalid service.');
        }

        $this->validateServiceLocation($serviceLocation);

        $dateObject = $this->parseDate($date);

        $this->validateNotPastDate(
            $dateObject,
            'You cannot book an appointment for a past date.'
        );

        $this->validateTime($time);
        // ---------------------------------------------------------
        // Validate barber and shop
        // ---------------------------------------------------------

        $barber = $this->getBookableBarber($barberId);

        $shop = $this->getBookableShopForBarber(
            $barber,
            $serviceLocation
        );

        // ---------------------------------------------------------
        // Resolve service and duration
        // ---------------------------------------------------------

        $durationMinutes = $this->resolveServiceDuration(
            $barberId,
            $serviceId,
            $serviceLocation,
            $shop
        );

        // ---------------------------------------------------------
        // Validate barber schedule
        // ---------------------------------------------------------

        $day = $dateObject->format('l');

        $schedule = $this->getBarberSchedule(
            $barberId,
            $day,
            $serviceLocation
        );

        if (!$schedule) {
            throw new Exception(
                "The barber does not work on {$day}s for {$serviceLocation} services."
            );
        }

        // ---------------------------------------------------------
        // Validate appointment time against schedule
        // ---------------------------------------------------------

        $appointmentStart = new DateTime(
            $date . ' ' . $time . ':00'
        );

        $appointmentEnd = (clone $appointmentStart)->modify(
            '+' . $durationMinutes . ' minutes'
        );

        $this->validateAppointmentWithinSchedule(
            $appointmentStart,
            $appointmentEnd,
            $date,
            $schedule
        );

        // ---------------------------------------------------------
        // Begin transaction
        // ---------------------------------------------------------

        $this->appointmentRepository->beginTransaction();

        try {
            /*
             * Lock the barber/date resource.
             *
             * This protects the barber's entire calendar for this
             * date, including both SHOP and HOME appointments.
             */
            $this->appointmentRepository->lockBarberDate(
                $barber->id,
                $date
            );

            /*
             * IMPORTANT:
             *
             * Existing appointments are queried AFTER acquiring
             * the lock.
             */
            // -----------------------------------------------------
// Final customer appointment check
// -----------------------------------------------------

            $existingCustomerAppointment =
                $this->appointmentRepository->findActiveByCustomer(
                    $customerId
                );

            if ($existingCustomerAppointment) {
                throw new BookingConflictException(
                    'You already have an active appointment. Please complete or cancel it before booking another appointment.'
                );

            }

// -----------------------------------------------------
// Final barber conflict check
// -----------------------------------------------------

            $existingAppointments =
                $this->appointmentRepository->findByBarberAndDate(
                    $barber->id,
                    $date
                );

            $this->ensureNoAppointmentConflict(
                $appointmentStart,
                $appointmentEnd,
                $existingAppointments
            );

            // -----------------------------------------------------
            // Prepare appointment
            // -----------------------------------------------------

            $bookingCode = null;

            if ($serviceLocation === 'SHOP') {
                $bookingCode = $this->generateBookingCode();
            }

            $appointmentData = [
                'customer_id' => $customerId,

                'shop_id' => $serviceLocation === 'SHOP'
                    ? $shop?->id
                    : null,

                'barber_id' => $barber->id,
                'service_id' => $serviceId,
                'service_location' => $serviceLocation,
                'duration_minutes' => $durationMinutes,
                'appointment_date' => $date,
                'appointment_time' => $time,

                'booking_code' => $bookingCode,
            ];

            // -----------------------------------------------------
            // Create appointment
            // -----------------------------------------------------

            $created = $this->appointmentRepository->create(
                $appointmentData
            );

            if (!$created) {
                throw new Exception(
                    'Unable to create appointment.'
                );
            }

            // -----------------------------------------------------
            // Commit
            // -----------------------------------------------------

            $this->appointmentRepository->commit();

            return $bookingCode;

        } catch (\Throwable $e) {
            $this->appointmentRepository->rollback();

            throw $e;
        }
    }

    /**
     * Get all appointments belonging to a customer.
     */
    public function getCustomerAppointments(
        int $customerId
    ): array {
        if ($customerId <= 0) {
            throw new Exception('Invalid customer.');
        }

        return $this->appointmentRepository
            ->findByCustomer($customerId);
    }

    /**
     * Get appointments for a shop owned by the authenticated user.
     */
    public function getShopOwnerAppointments(
        int $ownerId,
        int $shopId
    ): array {
        if ($ownerId <= 0) {
            throw new Exception('Invalid shop owner.');
        }

        if ($shopId <= 0) {
            throw new Exception('Invalid shop.');
        }

        $shop = $this->shopRepository->findById($shopId);

        if (!$shop) {
            throw new Exception('Shop not found.');
        }

        if ((int) $shop->ownerId !== $ownerId) {
            throw new Exception(
                'You are not authorized to view bookings for this shop.'
            );
        }

        return $this->appointmentRepository->findByShop($shopId);
    }

    /**
     * Get barber availability information.
     *
     * This endpoint returns the barber's schedule and currently
     * booked appointment intervals.
     */
    public function getBarberAvailability(
        int $barberId,
        string $date,
        string $serviceLocation
    ): array {
        if ($barberId <= 0) {
            throw new Exception('Invalid barber.');
        }

        $serviceLocation = strtoupper(
            trim($serviceLocation)
        );

        $this->validateServiceLocation($serviceLocation);

        $dateObject = $this->parseDate($date);

        $this->validateNotPastDate(
            $dateObject,
            'You cannot view availability for a past date.'
        );

        $barber = $this->getBookableBarber($barberId);

        $this->getBookableShopForBarber(
            $barber,
            $serviceLocation
        );

        $day = $dateObject->format('l');

        $schedule = $this->getBarberSchedule(
            $barberId,
            $day,
            $serviceLocation
        );

        if (!$schedule) {
            return [
                'date' => $date,
                'day' => $day,
                'service_location' => $serviceLocation,
                'working' => false,
                'start_time' => null,
                'end_time' => null,
                'booked' => [],
            ];
        }

        /*
         * Do not filter appointments by service location.
         *
         * SHOP and HOME appointments compete for the same barber's
         * time.
         */
        $appointments =
            $this->appointmentRepository->findByBarberAndDate(
                $barberId,
                $date
            );

        $booked = [];

        foreach ($appointments as $appointment) {
            $start = new DateTime(
                $date . ' ' . $appointment['appointment_time']
            );

            $end = (clone $start)->modify(
                '+' . (int) $appointment['duration_minutes'] . ' minutes'
            );

            $booked[] = [
                'appointment_id' => (int) $appointment['id'],
                'start_time' => $start->format('H:i'),
                'end_time' => $end->format('H:i'),
                'duration_minutes' => (int) $appointment['duration_minutes'],
                'service_location' => $appointment['service_location'],
                'status' => $appointment['status'],
            ];
        }

        return [
            'date' => $date,
            'day' => $day,
            'service_location' => $serviceLocation,
            'working' => true,
            'start_time' => $schedule['start_time'],
            'end_time' => $schedule['end_time'],
            'booked' => $booked,
        ];
    }

    /**
     * Generate all candidate booking slots.
     *
     * Every slot is returned:
     *
     *     available
     *     booked
     *
     * The frontend is responsible for displaying booked slots
     * as disabled.
     */
    public function getAvailableSlots(
        int $barberId,
        int $serviceId,
        string $serviceLocation,
        string $date
    ): array {
        if ($barberId <= 0) {
            throw new Exception('Invalid barber.');
        }

        if ($serviceId <= 0) {
            throw new Exception('Invalid service.');
        }

        $serviceLocation = strtoupper(
            trim($serviceLocation)
        );

        $this->validateServiceLocation($serviceLocation);

        $dateObject = $this->parseDate($date);

        $this->validateNotPastDate(
            $dateObject,
            'You cannot view availability for a past date.'
        );

        // ---------------------------------------------------------
        // Validate barber and shop
        // ---------------------------------------------------------

        $barber = $this->getBookableBarber($barberId);

        $shop = $this->getBookableShopForBarber(
            $barber,
            $serviceLocation
        );

        // ---------------------------------------------------------
        // Resolve service and duration
        // ---------------------------------------------------------

        $durationMinutes = $this->resolveServiceDuration(
            $barberId,
            $serviceId,
            $serviceLocation,
            $shop
        );

        // ---------------------------------------------------------
        // Get schedule
        // ---------------------------------------------------------

        $day = $dateObject->format('l');

        $schedule = $this->getBarberSchedule(
            $barberId,
            $day,
            $serviceLocation
        );

        /*
         * No schedule means the barber is unavailable on this
         * day/location.
         */
        if (!$schedule) {
            return [];
        }

        $scheduleStart = new DateTime(
            $date . ' ' . $schedule['start_time']
        );

        $scheduleEnd = new DateTime(
            $date . ' ' . $schedule['end_time']
        );

        // ---------------------------------------------------------
        // Get all active appointments
        // ---------------------------------------------------------

        /*
         * IMPORTANT:
         *
         * We intentionally fetch appointments across BOTH SHOP
         * and HOME locations.
         */
        $existingAppointments =
            $this->appointmentRepository->findByBarberAndDate(
                $barberId,
                $date
            );

        // ---------------------------------------------------------
        // Generate 30-minute slots
        // ---------------------------------------------------------

        $slots = [];

        $slot = clone $scheduleStart;

        $now = new DateTime();

        while (true) {
            $slotStart = clone $slot;

            $slotEnd = (clone $slotStart)->modify(
                '+' . $durationMinutes . ' minutes'
            );

            /*
             * The complete service must fit inside the barber's
             * working hours.
             */
            if ($slotEnd > $scheduleEnd) {
                break;
            }

            /*
             * Do not show times that have already passed today.
             */
            if (
                $dateObject->format('Y-m-d') ===
                $now->format('Y-m-d')
                &&
                $slotStart < $now
            ) {
                $slot->modify('+30 minutes');
                continue;
            }

            $isBooked = false;

            // -----------------------------------------------------
            // Check overlap
            // -----------------------------------------------------

            foreach ($existingAppointments as $appointment) {
                $existingStart = new DateTime(
                    $appointment['appointment_date']
                    . ' '
                    . $appointment['appointment_time']
                );

                $existingEnd = (clone $existingStart)->modify(
                    '+'
                    . (int) $appointment['duration_minutes']
                    . ' minutes'
                );

                /*
                 * Overlap formula:
                 *
                 * slotStart < existingEnd
                 * AND
                 * slotEnd > existingStart
                 *
                 * This allows adjacent appointments.
                 */
                if (
                    $slotStart < $existingEnd &&
                    $slotEnd > $existingStart
                ) {
                    $isBooked = true;
                    break;
                }
            }

            $slots[] = [
                'time' => $slotStart->format('H:i'),
                'end_time' => $slotEnd->format('H:i'),
                'status' => $isBooked
                    ? 'booked'
                    : 'available',
            ];

            $slot->modify('+30 minutes');
        }

        return $slots;
    }

    // =============================================================
    // PRIVATE VALIDATION / RESOLUTION HELPERS
    // =============================================================

    /**
     * Validate service location.
     */
    private function validateServiceLocation(
        string $serviceLocation
    ): void {
        if (!in_array(
            $serviceLocation,
            ['SHOP', 'HOME'],
            true
        )) {
            throw new Exception(
                'Invalid service location. Please use SHOP or HOME.'
            );
        }
    }

    /**
     * Parse and validate an appointment date.
     */
    private function parseDate(string $date): DateTime
    {
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

        return $dateObject;
    }

    /**
     * Prevent booking/viewing availability for past dates.
     */
    private function validateNotPastDate(
        DateTime $date,
        string $message
    ): void {
        $today = new DateTime('today');

        if ($date < $today) {
            throw new Exception($message);
        }
    }

    /**
     * Validate appointment time format.
     */
    private function validateTime(string $time): void
    {
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
    }

    /**
     * Find and validate a bookable barber.
     */
    private function getBookableBarber(int $barberId)
    {
        $barber = $this->barberRepository->findById(
            $barberId
        );

        if (!$barber) {
            throw new Exception(
                'Barber not found.'
            );
        }

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

        return $barber;
    }

    /**
     * Resolve and validate the barber's shop.
     *
     * Returns null for an independent barber providing HOME service.
     */
    private function getBookableShopForBarber(
        $barber,
        string $serviceLocation
    ) {
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

        /*
         * Independent barbers cannot provide SHOP services.
         */
        if (
            $serviceLocation === 'SHOP' &&
            $barber->shopId === null
        ) {
            throw new Exception(
                'Independent barbers cannot provide shop services.'
            );
        }

        return $shop;
    }

    /**
     * Resolve the requested service and return its duration.
     */
    private function resolveServiceDuration(
        int $barberId,
        int $serviceId,
        string $serviceLocation,
        $shop
    ): int {
        if ($serviceLocation === 'SHOP') {
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

            if (
                $shop === null ||
                (int) $service->shopId !== (int) $shop->id
            ) {
                throw new Exception(
                    'This service does not belong to the barber\'s shop.'
                );
            }

            /*
             * If the service is assigned to a specific barber,
             * the selected barber must match.
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

            if (
                (int) $homeService->barberId !== $barberId
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

        return $durationMinutes;
    }

    /**
     * Find the barber's schedule for a day and location.
     */
    private function getBarberSchedule(
        int $barberId,
        string $day,
        string $serviceLocation
    ): ?array {
        return $this->barberScheduleRepository
            ->findByBarberAndDay(
                $barberId,
                $day,
                $serviceLocation
            );
    }

    /**
     * Ensure an appointment fits completely inside the schedule.
     */
    private function validateAppointmentWithinSchedule(
        DateTime $appointmentStart,
        DateTime $appointmentEnd,
        string $date,
        array $schedule
    ): void {
        $scheduleStart = new DateTime(
            $date . ' ' . $schedule['start_time']
        );

        $scheduleEnd = new DateTime(
            $date . ' ' . $schedule['end_time']
        );

        if ($appointmentStart < $scheduleStart) {
            throw new BookingConflictException(
                'The selected time conflicts with another appointment.'
            );
        }

        if ($appointmentEnd > $scheduleEnd) {
            throw new Exception(
                'The appointment extends beyond the barber\'s working hours.'
            );
        }
    }

    /**
     * Ensure the requested appointment does not overlap
     * any active appointment.
     */
    private function ensureNoAppointmentConflict(
        DateTime $appointmentStart,
        DateTime $appointmentEnd,
        array $existingAppointments
    ): void {
        foreach ($existingAppointments as $existingAppointment) {
            $existingStart = new DateTime(
                $existingAppointment['appointment_date']
                . ' '
                . $existingAppointment['appointment_time']
            );

            $existingEnd = (clone $existingStart)->modify(
                '+'
                . (int) $existingAppointment['duration_minutes']
                . ' minutes'
            );

            /*
             * Two time ranges overlap when:
             *
             * new start < existing end
             * AND
             * new end > existing start
             *
             * Therefore:
             *
             * 10:00 - 11:00
             * 11:00 - 12:00
             *
             * is allowed, while:
             *
             * 10:00 - 11:00
             * 10:30 - 11:30
             *
             * is rejected.
             */
            if (
                $appointmentStart < $existingEnd &&
                $appointmentEnd > $existingStart
            ) {
                throw new BookingConflictException(
                    'The barber is already booked during the selected time.'
                );
            }
        }
    }

    private function generateBookingCode(): string
    {
        return (string) random_int(10000, 99999);
    }


}