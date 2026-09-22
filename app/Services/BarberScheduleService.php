<?php

namespace App\Services;

use App\Repositories\BarberRepository;
use App\Repositories\BarberScheduleRepository;
use Exception;

class BarberScheduleService
{
    private BarberScheduleRepository $scheduleRepository;
    private BarberRepository $barberRepository;

    public function __construct(
        BarberScheduleRepository $scheduleRepository,
        BarberRepository $barberRepository
    ) {
        $this->scheduleRepository = $scheduleRepository;
        $this->barberRepository = $barberRepository;
    }

    /**
     * Get the authenticated barber's schedules.
     */
    public function getMySchedule(int $userId): array
    {
        $barber = $this->getActiveBarber($userId);

        return $this->scheduleRepository->findByBarber($barber['id']);
    }

    /**
     * Create a schedule for the authenticated barber.
     */
    public function create(int $userId, array $data): bool
    {
        $barber = $this->getActiveBarber($userId);

        $day = trim($data['day'] ?? '');
        $serviceLocation = strtoupper(trim($data['service_location'] ?? ''));
        $startTime = trim($data['start_time'] ?? '');
        $endTime = trim($data['end_time'] ?? '');

        $this->validateDay($day);
        $this->validateServiceLocation($serviceLocation);
        $this->validateTimes($startTime, $endTime);

        /*
         * Prevent an independent barber from creating a SHOP schedule.
         */
        if ($serviceLocation === 'SHOP' && $barber['shop_id'] === null) {
            throw new Exception(
                'Independent barbers cannot create a SHOP schedule.'
            );
        }

        /*
         * Prevent duplicate schedules for the same
         * barber + day + service location.
         */
        $existing = $this->scheduleRepository->findByBarberAndDay(
            (int) $barber['id'],
            $day,
            $serviceLocation
        );

        if ($existing) {
            throw new Exception(
                "A {$serviceLocation} schedule already exists for {$day}."
            );
        }

        return $this->scheduleRepository->create([
            'barber_id' => (int) $barber['id'],
            'day' => $day,
            'service_location' => $serviceLocation,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }

    /**
     * Get the authenticated and active barber.
     */
    private function getActiveBarber(int $userId): array
    {
        $barber = $this->barberRepository->findByUserId($userId);

        if (!$barber) {
            throw new Exception(
                'You must create a barber profile before managing your schedule.'
            );
        }

        if (($barber['approval_status'] ?? '') !== 'approved') {
            throw new Exception(
                'Your barber profile has not been approved.'
            );
        }

        if (($barber['status'] ?? '') !== 'active') {
            throw new Exception(
                'Your barber profile is not active.'
            );
        }

        return $barber;
    }

    /**
     * Validate the day.
     */
    private function validateDay(string $day): void
    {
        $validDays = [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday',
        ];

        if (!in_array($day, $validDays, true)) {
            throw new Exception(
                'Invalid day. Please use Monday through Sunday.'
            );
        }
    }

    /**
     * Validate service location.
     */
    private function validateServiceLocation(string $serviceLocation): void
    {
        $validLocations = [
            'SHOP',
            'HOME',
        ];

        if (!in_array($serviceLocation, $validLocations, true)) {
            throw new Exception(
                'Invalid service location. Please use SHOP or HOME.'
            );
        }
    }

    /**
     * Validate start and end times.
     */
    private function validateTimes(
        string $startTime,
        string $endTime
    ): void {
        if (!$this->isValidTime($startTime)) {
            throw new Exception(
                'Invalid start time. Use HH:MM format.'
            );
        }

        if (!$this->isValidTime($endTime)) {
            throw new Exception(
                'Invalid end time. Use HH:MM format.'
            );
        }

        if ($startTime >= $endTime) {
            throw new Exception(
                'Start time must be earlier than end time.'
            );
        }
    }

    /**
     * Validate HH:MM time format.
     */
    private function isValidTime(string $time): bool
    {
        return preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                $time
            ) === 1;
    }
}
