<?php

use PHPUnit\Framework\TestCase;
use App\Models\Barber;
use App\Services\BookingService;
use App\Repositories\ShopRepository;
use App\Repositories\BarberRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\BarberHomeServiceRepository;
use App\Repositories\AppointmentRepository;
use App\Repositories\BarberScheduleRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\CustomerBookingRestrictionRepository;
use App\Repositories\BarberShopMembershipRepository;
use App\Repositories\NotificationRepository;

class ReproduceIssueTest extends TestCase
{
    private $bookingService;
    private $barberScheduleRepository;

    protected function setUp(): void
    {
        // Mocking all required repositories
        $shopRepo = $this->createMock(ShopRepository::class);
        $barberRepo = $this->createMock(BarberRepository::class);
        $serviceRepo = $this->createMock(ServiceRepository::class);
        $homeServiceRepo = $this->createMock(BarberHomeServiceRepository::class);
        $appointmentRepo = $this->createMock(AppointmentRepository::class);
        $this->barberScheduleRepository = $this->createMock(BarberScheduleRepository::class);
        $subscriptionRepo = $this->createMock(SubscriptionRepository::class);
        $restrictionRepo = $this->createMock(CustomerBookingRestrictionRepository::class);
        $membershipRepo = $this->createMock(BarberShopMembershipRepository::class);
        $notificationRepo = $this->createMock(NotificationRepository::class);

        $this->bookingService = new BookingService(
            $shopRepo,
            $barberRepo,
            $serviceRepo,
            $homeServiceRepo,
            $appointmentRepo,
            $this->barberScheduleRepository,
            $subscriptionRepo,
            $restrictionRepo,
            $membershipRepo,
            $notificationRepo
        );

        // Common barber mock
        $barber = new Barber([
            'id' => 1,
            'user_id' => 1,
            'shop_id' => 10,
            'approval_status' => 'approved',
            'status' => 'active'
        ]);
        $barberRepo->method('findById')->willReturn($barber);

        // Entitlement mocks
        $subscriptionRepo->method('hasActiveEntitlement')->willReturn(true);

        // Shop mocks
        $shop = new \App\Models\Shop([
            'id' => 10,
            'owner_id' => 1,
            'name' => 'Test Shop',
            'location' => 'Accra',
            'approval_status' => 'approved',
            'status' => 'active'
        ]);
        $shopRepo->method('findById')->willReturn($shop);

        // Membership mock
        $membershipRepo->method('findActiveByBarberId')->willReturn(['shop_id' => 10]);

        // Service mocks
        $service = new \App\Models\Service([
            'id' => 5,
            'shop_id' => 10,
            'barber_id' => null,
            'name' => 'Test Haircut',
            'duration_minutes' => 30,
            'price' => 50.00,
            'status' => 'active'
        ]);
        $serviceRepo->method('findById')->willReturn($service);

        $homeService = new \App\Models\BarberHomeService([
            'id' => 6,
            'barber_id' => 1,
            'name' => 'Home Haircut',
            'duration_minutes' => 30,
            'price' => 70.00,
            'status' => 'active'
        ]);
        $homeServiceRepo->method('findById')->willReturn($homeService);

        $appointmentRepo->method('findByBarberAndDate')->willReturn([]);
    }

    public function testGetAvailableSlotsForShopWhenBothSchedulesExist()
    {
        $date = '2026-12-01'; // Future date
        $day = 'Tuesday'; // 2026-12-01 is a Tuesday

        // Mock schedule: Barber has both SHOP and HOME schedules
        $this->barberScheduleRepository->method('findByBarberAndDay')
            ->willReturnCallback(function($barberId, $day, $location) {
                if ($location === 'SHOP') {
                    return [
                        'start_time' => '08:00',
                        'end_time' => '12:00',
                        'service_location' => 'SHOP'
                    ];
                }
                if ($location === 'HOME') {
                    return [
                        'start_time' => '13:00',
                        'end_time' => '17:00',
                        'service_location' => 'HOME'
                    ];
                }
                return null;
            });

        // Request SHOP slots
        $slots = $this->bookingService->getAvailableSlots(1, 5, 'SHOP', $date);
        
        $this->assertNotEmpty($slots, 'Slots should not be empty for SHOP');
        $this->assertEquals('08:00', $slots[0]['time']);
    }

    public function testGetAvailableSlotsForHomeWhenBothSchedulesExist()
    {
        $date = '2026-12-01';
        $day = 'Tuesday';

        $this->barberScheduleRepository->method('findByBarberAndDay')
            ->willReturnCallback(function($barberId, $day, $location) {
                if ($location === 'SHOP') {
                    return [
                        'start_time' => '08:00',
                        'end_time' => '12:00',
                        'service_location' => 'SHOP'
                    ];
                }
                if ($location === 'HOME') {
                    return [
                        'start_time' => '13:00',
                        'end_time' => '17:00',
                        'service_location' => 'HOME'
                    ];
                }
                return null;
            });

        // Request HOME slots
        $slots = $this->bookingService->getAvailableSlots(1, 6, 'HOME', $date);
        
        $this->assertNotEmpty($slots, 'Slots should not be empty for HOME');
        $this->assertEquals('13:00', $slots[0]['time']);
    }
}
