<?php

declare(strict_types=1);
//namespace Api\Controllers\Booking;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


// CORS
$allowedOrigins = [
    'http://localhost:5173',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../bootstrap.php';

use Api\Controllers\SubscriptionController;
use Api\Controllers\Auth\AuthController;
use Api\Controllers\Profile\ProfileController;
use Api\Controllers\Admin\AdminController;

use App\Repositories\PlanRepository;
use App\Repositories\PlanVersionRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\SubscriptionPaymentRepository;
use App\Repositories\UserRepository;
use App\Repositories\SubscriptionEventRepository;
use App\Services\PaystackService;
use App\Services\SubscriptionService;





$subscriptionService = new SubscriptionService(
    new PlanRepository(),
    new PlanVersionRepository(),
    new SubscriptionRepository(),
    new SubscriptionPaymentRepository(),
    new UserRepository(),
    new SubscriptionEventRepository(),
    new PaystackService()
);

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$projectPath = '/barber-backend/public';

if (strpos($requestUri, $projectPath) === 0) {
    $requestUri = substr($requestUri, strlen($projectPath));
}

$requestMethod = $_SERVER['REQUEST_METHOD'];



// AUTH ROUTES
if ($requestUri === '/api/auth/register' && $requestMethod === 'POST') {

    (new AuthController())->register();

} elseif ($requestUri === '/api/auth/verify-email' && $requestMethod === 'POST') {

    (new AuthController())->verifyEmail();

} elseif ($requestUri === '/api/auth/resend-verification' && $requestMethod === 'POST') {

    (new AuthController())->resendVerification();

} elseif ($requestUri === '/api/auth/login' && $requestMethod === 'POST') {

    (new AuthController())->login();

} elseif ($requestUri === '/api/auth/refresh' && $requestMethod === 'POST') {

    (new AuthController())->refresh();

} elseif ($requestUri === '/api/auth/logout' && $requestMethod === 'POST') {

    (new AuthController())->logout();

} elseif ($requestUri === '/api/auth/password/forgot' && $requestMethod === 'POST') {

    (new AuthController())->forgotPassword();

} elseif ($requestUri === '/api/auth/password/verify' && $requestMethod === 'POST') {

    (new AuthController())->verifyResetCode();

} elseif ($requestUri === '/api/auth/password/reset' && $requestMethod === 'POST') {

    (new AuthController())->resetPassword();



// BARBER ROUTES
} elseif ($requestUri === '/api/barber/profile' && $requestMethod === 'POST') {
    (new BarberController())->createProfile();

} elseif ($requestUri === '/api/barber/shop-application' && $requestMethod === 'POST') {
    (new BarberController())->applyToShop();

    // BARBER SCHEDULE ROUTES
} elseif (
    $requestUri === '/api/barber/schedule'
    && $requestMethod === 'GET'
) {
    (new BarberScheduleController())->getMySchedule();

} elseif (
    $requestUri === '/api/barber/schedule'
    && $requestMethod === 'POST'
) {
    (new BarberScheduleController())->create();

} elseif (
    preg_match('#^/api/barber/schedule/([0-9]+)$#', $requestUri, $matches)
    && $requestMethod === 'PUT'
) {
    (new BarberScheduleController())->update((int) $matches[1]);

} elseif (
    preg_match('#^/api/barber/schedule/([0-9]+)$#', $requestUri, $matches)
    && $requestMethod === 'DELETE'
) {
    (new BarberScheduleController())->delete((int) $matches[1]);

    // BARBER HOME SERVICE ROUTES
} elseif (
    $requestUri === '/api/barber/home-services'
    && $requestMethod === 'POST'
) {
    (new HomeServiceController())->create();

} elseif (
    $requestUri === '/api/barber/home-services'
    && $requestMethod === 'GET'
) {
    (new HomeServiceController())->getMyServices();

} elseif (
    preg_match(
        '#^/api/barber/home-services/([0-9]+)$#',
        $requestUri,
        $matches
    )
    && $requestMethod === 'PUT'
) {
    (new HomeServiceController())->update(
        (int) $matches[1]
    );

} elseif (
    preg_match(
        '#^/api/barber/home-services/([0-9]+)/status$#',
        $requestUri,
        $matches
    )
    && $requestMethod === 'PATCH'
) {
    (new HomeServiceController())->updateStatus(
        (int) $matches[1]
    );


// SHOP OWNER BARBER ROUTES
} elseif ($requestUri === '/api/shop-owner/barbers' && $requestMethod === 'GET') {
    (new BarberController())->getMyBarbers();
} elseif (
    preg_match(
        '#^/api/shop-owner/barbers/([0-9]+)/approval$#',
        $requestUri,
        $matches
    ) &&
    $requestMethod === 'PATCH'
) {
    (new BarberController())->updateApproval(
        (int) $matches[1]
    );





    // BARBER DISCOVERY ROUTE
} elseif (
    $requestUri === '/api/barbers'
    && $requestMethod === 'GET'
) {
    (new BarberDiscoveryController())->getBarbers();


// BARBER PUBLIC SERVICE ROUTES
} elseif (
    preg_match(
        '#^/api/barbers/([0-9]+)/shop-services$#',
        $requestUri,
        $matches
    )
    && $requestMethod === 'GET'
) {
    (new BarberServiceController())->getShopServices(
        (int) $matches[1]
    );

} elseif (
    preg_match(
        '#^/api/barbers/([0-9]+)/home-services$#',
        $requestUri,
        $matches
    )
    && $requestMethod === 'GET'
) {
    (new BarberServiceController())->getHomeServices(
        (int) $matches[1]
    );


    //BOOKING ROUTES
} elseif ($requestUri === '/api/bookings' && $requestMethod === 'POST') {
    (new BookingController())->bookAppointment();

    //STORE ROUTES
} elseif ($requestUri === '/api/products' && $requestMethod === 'GET') {

    (new StoreController())->getProducts();

} elseif ($requestUri === '/api/orders' && $requestMethod === 'POST') {

    (new StoreController())->placeOrder();


    // SUBSCRIPTION ROUTES
} elseif (
    $requestUri === '/api/subscriptions'
    && $requestMethod === 'POST'
) {
    (new SubscriptionController($subscriptionService))->create();

} elseif (
    $requestUri === '/api/subscriptions/verify'
    && $requestMethod === 'POST'
) {
    (new SubscriptionController($subscriptionService))->verify();

} elseif (
    $requestUri === '/api/subscriptions/payment/resume'
    && $requestMethod === 'POST'
) {
    (new SubscriptionController($subscriptionService))->resumePayment();

} elseif (
    $requestUri === '/api/subscriptions/pricing'
    && $requestMethod === 'GET'
) {
    (new SubscriptionController($subscriptionService))->getPricing();

    // Finance Manager proposes pricing
} elseif (
    $requestUri === '/api/subscriptions/pricing'
    && $requestMethod === 'POST'
) {
    (new SubscriptionController($subscriptionService))->proposePricing();

// Get current subscription
} elseif (
    $requestUri === '/api/subscriptions/current'
    && $requestMethod === 'GET'
) {
    (new SubscriptionController($subscriptionService))->getCurrent();

} elseif (
    $requestUri === '/api/subscriptions/current'
    && $requestMethod === 'GET'
) {
    (new SubscriptionController($subscriptionService))->getCurrent();

} elseif (
    $requestUri === '/api/subscriptions/pricing/pending'
    && $requestMethod === 'GET'
) {
    (new SubscriptionController($subscriptionService))->getPendingPricing();

} elseif (
    $requestUri === '/api/subscriptions/pricing/approval'
    && $requestMethod === 'POST'
) {
    (new SubscriptionController($subscriptionService))->updatePricingApproval();


    // ADMIN ROUTES
} elseif (
    $requestUri === '/api/admin/shops/pending'
    && $requestMethod === 'GET'
) {
    (new AdminController())->getPendingShops();

} elseif (
    preg_match('#^/api/admin/shops/([0-9]+)/approval$#', $requestUri, $matches)
    && $requestMethod === 'PATCH'
) {
    (new AdminController())->updateShopApproval((int) $matches[1]);


    //404
} else {

    http_response_code(404);

    header('Content-Type: application/json; charset=UTF-8');

    echo json_encode([
        'status' => 'error',
        'message' => 'Endpoint not found'
    ]);
}