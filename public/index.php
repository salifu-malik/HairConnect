<?php

declare(strict_types=1);

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
}


header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../bootstrap.php';

use Api\Controllers\Auth\AuthController;
use Api\Controllers\BookingController;
use Api\Controllers\StoreController;
use Api\Controllers\ProfileController;
use Api\Controllers\Barber\BarberController;
use Api\Controllers\ShopOwner\ShopController;
use App\Helpers\DatabaseManager;
use Api\Controllers\Admin\AdminController;

$config = require __DIR__ . '/../config/database.php';

DatabaseManager::init($config);

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$projectPath = '/barber-backend/public';

if (strpos($requestUri, $projectPath) === 0) {
    $requestUri = substr($requestUri, strlen($projectPath));
}

$requestMethod = $_SERVER['REQUEST_METHOD'];



//AUTH ROUTES


if ($requestUri === '/api/auth/register' && $requestMethod === 'POST') {

    (new AuthController())->register();

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

} elseif ($requestUri === '/api/profile' && $requestMethod === 'GET') {

    (new ProfileController())->getProfile();

} elseif ($requestUri === '/api/profile' && $requestMethod === 'PUT') {

    (new ProfileController())->updateProfile();

} elseif ($requestUri === '/api/profile/change-password' && $requestMethod === 'POST') {
    (new ProfileController())->changePassword();
} elseif ($requestUri === '/api/shops' && $requestMethod === 'POST') {
    (new ShopController())->createShop();
} elseif ($requestUri === '/api/shops/my' && $requestMethod === 'GET') {
    (new ShopController())->getMyShops();
} elseif ($requestUri === '/api/shops/available' && $requestMethod === 'GET') {
    (new ShopController())->getAvailableShops();


// BARBER ROUTES

} elseif ($requestUri === '/api/barber/profile' && $requestMethod === 'POST') {
    (new BarberController())->createProfile();

} elseif ($requestUri === '/api/barber/shop-application' && $requestMethod === 'POST') {
    (new BarberController())->applyToShop();


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



    //BOOKING ROUTES
} elseif ($requestUri === '/api/bookings' && $requestMethod === 'POST') {
    (new BookingController())->bookAppointment();


    //STORE ROUTES
} elseif ($requestUri === '/api/products' && $requestMethod === 'GET') {

    (new StoreController())->getProducts();

} elseif ($requestUri === '/api/orders' && $requestMethod === 'POST') {

    (new StoreController())->placeOrder();


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