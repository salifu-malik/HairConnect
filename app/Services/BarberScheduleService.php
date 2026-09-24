<?php

namespace App\Services;

use App\Models\Barber;
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


     //Get the authenticated barber's schedules.
    public function getMySchedule(int $userId): array
    {
        $barber = $this->getActiveBarber($userId);

        return $this->scheduleRepository->findByBarber(
            $barber->id
        );
    }


     //Create a schedule for the authenticated barber.
    public function create(int $userId, array $data): bool
    {
        $barber = $this->getActiveBarber($userId);

        $day = trim((string) ($data['day'] ?? ''));
        $serviceLocation = strtoupper(
            trim((string) ($data['service_location'] ?? ''))
        );
        $startTime = trim((string) ($data['start_time'] ?? ''));
        $endTime = trim((string) ($data['end_time'] ?? ''));

        $this->validateScheduleData(
            $day,
            $serviceLocation,
            $startTime,
            $endTime
        );

        $this->validateServiceLocationForBarber(
            $barber,
            $serviceLocation
        );

        /*
         * Prevent duplicate schedules for:
         * barber + day + service location.
         */
        $existing = $this->scheduleRepository->findByBarberAndDay(
            $barber->id,
            $day,
            $serviceLocation
        );

        if ($existing) {
            throw new Exception(
                "A {$serviceLocation} schedule already exists for {$day}."
            );
        }

        return $this->scheduleRepository->create([
            'barber_id' => $barber->id,
            'day' => $day,
            'service_location' => $serviceLocation,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }


     //Update a schedule belonging to the authenticated barber.
    public function update(
        int $userId,
        int $scheduleId,
        array $data
    ): bool {
        $barber = $this->getActiveBarber($userId);

        $schedule = $this->scheduleRepository->findById(
            $scheduleId
        );

        if (!$schedule) {
            throw new Exception(
                'Schedule not found.'
            );
        }

        /*
         * Make sure the authenticated barber owns
         * this schedule.
         */
        if ((int) $schedule['barber_id'] !== $barber->id) {
            throw new Exception(
                'You are not authorized to update this schedule.'
            );
        }

        $day = trim((string) ($data['day'] ?? ''));
        $serviceLocation = strtoupper(
            trim((string) ($data['service_location'] ?? ''))
        );
        $startTime = trim((string) ($data['start_time'] ?? ''));
        $endTime = trim((string) ($data['end_time'] ?? ''));

        $this->validateScheduleData(
            $day,
            $serviceLocation,
            $startTime,
            $endTime
        );

        $this->validateServiceLocationForBarber(
            $barber,
            $serviceLocation
        );

        /*
         * Check whether another schedule belonging to
         * this barber already uses the new day/location.
         */
        $existing = $this->scheduleRepository->findByBarberAndDay(
            $barber->id,
            $day,
            $serviceLocation
        );

        if (
            $existing
            && (int) $existing['id'] !== $scheduleId
        ) {
            throw new Exception(
                "A {$serviceLocation} schedule already exists for {$day}."
            );
        }

        return $this->scheduleRepository->update(
            $scheduleId,
            [
                'day' => $day,
                'service_location' => $serviceLocation,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]
        );
    }


     //Delete a schedule belonging to the authenticated barber.
    public function delete(
        int $userId,
        int $scheduleId
    ): bool {
        $barber = $this->getActiveBarber($userId);

        $schedule = $this->scheduleRepository->findById(
            $scheduleId
        );

        if (!$schedule) {
            throw new Exception(
                'Schedule not found.'
            );
        }

        /*
         * Make sure the authenticated barber owns
         * this schedule.
         */
        if ((int) $schedule['barber_id'] !== $barber->id) {
            throw new Exception(
                'You are not authorized to delete this schedule.'
            );
        }

        return $this->scheduleRepository->delete(
            $scheduleId
        );
    }


     //Get the authenticated and active barber.
    private function getActiveBarber(int $userId): Barber
    {
        $barber = $this->barberRepository->findByUserId($userId);

        if (!$barber) {
            throw new Exception(
                'You must create a barber profile before managing your schedule.'
            );
        }

        if ($barber->approvalStatus !== 'approved') {
            throw new Exception(
                'Your barber profile has not been approved.'
            );
        }

        if ($barber->status !== 'active') {
            throw new Exception(
                'Your barber profile is not active.'
            );
        }

        return $barber;
    }


     //Validate schedule data.
    private function validateScheduleData(
        string $day,
        string $serviceLocation,
        string $startTime,
        string $endTime
    ): void {
        $this->validateDay($day);
        $this->validateServiceLocation($serviceLocation);
        $this->validateTimes($startTime, $endTime);
    }


     //Validate service location against barber type.
    private function validateServiceLocationForBarber(
        Barber $barber,
        string $serviceLocation
    ): void {
        /*
         * Independent barbers do not have a shop.
         * Therefore they cannot create or update
         * a schedule to SHOP.
         */
        if (
            $serviceLocation === 'SHOP'
            && $barber->shopId === null
        ) {
            throw new Exception(
                'Independent barbers cannot use SHOP schedules.'
            );
        }
    }


     //Validate the day.
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


    //Validate service location.
    private function validateServiceLocation(
        string $serviceLocation
    ): void {
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


     //Validate start and end times.
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


     //Validate HH:MM time format.
    private function isValidTime(string $time): bool
    {
        return preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                $time
            ) === 1;
    }
}
