<?php

use App\Controllers\AuthController;
use App\Controllers\BillingController;
use App\Controllers\DashboardController;
use App\Controllers\DealerController;
use App\Controllers\OrderController;
use App\Controllers\PaymentController;
use App\Controllers\ProfileController;
use App\Controllers\VehicleController;
use App\Core\Router;

$router = new Router();

// Public
$router->get('/', [DashboardController::class, 'home']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->get('/dealers/register', [DealerController::class, 'showRegister']);
$router->post('/dealers/register', [DealerController::class, 'register']);

// Auth required
$router->get('/dashboard', [DashboardController::class, 'index'], ['require_auth']);
$router->get('/profile', [ProfileController::class, 'index'], ['require_auth']);
$router->post('/profile', [ProfileController::class, 'update'], ['require_auth']);
$router->post('/profile/password', [ProfileController::class, 'changePassword'], ['require_auth']);

// Dealers
$router->get('/dealers', [DealerController::class, 'index'], ['require_auth']);
$router->post('/dealers', [DealerController::class, 'store'], ['require_auth']);
$router->get('/dealers/{id}', [DealerController::class, 'show'], ['require_auth']);
$router->post('/dealers/{id}/approve', [DealerController::class, 'approve'], ['require_auth']);
$router->post('/dealers/{id}/documents', [DealerController::class, 'uploadDocument'], ['require_auth']);

// Vehicles
$router->get('/vehicles', [VehicleController::class, 'index'], ['require_auth']);
$router->post('/vehicles', [VehicleController::class, 'store'], ['require_auth']);
$router->post('/vehicles/{id}', [VehicleController::class, 'update'], ['require_auth']);
$router->post('/vehicles/{id}/delete', [VehicleController::class, 'destroy'], ['require_auth']);
$router->post('/vehicles/{id}/variants', [VehicleController::class, 'addVariant'], ['require_auth']);
$router->post('/vehicles/{id}/images', [VehicleController::class, 'uploadImage'], ['require_auth']);
$router->get('/vehicles/{id}', [VehicleController::class, 'show'], ['require_auth']);

// Orders
$router->get('/orders', [OrderController::class, 'index'], ['require_auth']);
$router->post('/orders', [OrderController::class, 'store'], ['require_auth']);
$router->get('/orders/{id}', [OrderController::class, 'show'], ['require_auth']);
$router->post('/orders/{id}/status', [OrderController::class, 'updateStatus'], ['require_auth']);
$router->get('/orders/{id}/print', [OrderController::class, 'print'], ['require_auth']);

// Payments
$router->get('/payments', [PaymentController::class, 'index'], ['require_auth']);
$router->post('/payments', [PaymentController::class, 'store'], ['require_auth']);

// Billing
$router->get('/billing', [BillingController::class, 'index'], ['require_auth']);
$router->get('/billing/{id}', [BillingController::class, 'show'], ['require_auth']);
$router->get('/billing/{id}/preview', [BillingController::class, 'preview'], ['require_auth']);
$router->get('/billing/{id}/pdf', [BillingController::class, 'pdf'], ['require_auth']);
$router->post('/billing/warranty', [BillingController::class, 'createWarranty'], ['require_auth']);

return $router;
