<?php

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\BillingController;
use App\Controllers\DashboardController;
use App\Controllers\DealerController;
use App\Controllers\ExpenseController;
use App\Controllers\FinanceController;
use App\Controllers\HrController;
use App\Controllers\InventoryController;
use App\Controllers\LeadController;
use App\Controllers\NotificationController;
use App\Controllers\OrderController;
use App\Controllers\PartnerController;
use App\Controllers\PaymentController;
use App\Controllers\ProfileController;
use App\Controllers\ReportController;
use App\Controllers\SearchController;
use App\Controllers\ServiceController;
use App\Controllers\SparePartController;
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
$router->post('/leads/capture', [LeadController::class, 'capture']);

// Auth
$router->get('/dashboard', [DashboardController::class, 'index'], ['require_auth']);
$router->get('/profile', [ProfileController::class, 'index'], ['require_auth']);
$router->post('/profile', [ProfileController::class, 'update'], ['require_auth']);
$router->post('/profile/password', [ProfileController::class, 'changePassword'], ['require_auth']);
$router->get('/search', [SearchController::class, 'index'], ['require_auth']);

// Notifications
$router->get('/notifications', [NotificationController::class, 'index'], ['require_auth']);
$router->get('/notifications/unread-count', [NotificationController::class, 'unreadCount'], ['require_auth']);
$router->post('/notifications/read-all', [NotificationController::class, 'markAllRead'], ['require_auth']);
$router->post('/notifications/{id}/read', [NotificationController::class, 'markRead'], ['require_auth']);

// Dealers
$router->get('/dealers', [DealerController::class, 'index'], ['require_auth']);
$router->post('/dealers', [DealerController::class, 'store'], ['require_auth']);
$router->post('/dealers/{id}', [DealerController::class, 'update'], ['require_auth']);
$router->post('/dealers/{id}/delete', [DealerController::class, 'destroy'], ['require_auth']);
$router->get('/dealers/{id}', [DealerController::class, 'show'], ['require_auth']);
$router->post('/dealers/{id}/approve', [DealerController::class, 'approve'], ['require_auth']);
$router->post('/dealers/{id}/documents/{docId}/delete', [DealerController::class, 'destroyDocument'], ['require_auth']);
$router->post('/dealers/{id}/documents/{docId}', [DealerController::class, 'updateDocument'], ['require_auth']);
$router->post('/dealers/{id}/documents', [DealerController::class, 'uploadDocument'], ['require_auth']);
$router->post('/dealers/{id}/password', [DealerController::class, 'resetPassword'], ['require_auth']);
$router->post('/dealers/{id}/toggle-login', [DealerController::class, 'toggleUser'], ['require_auth']);

// Vehicles
$router->get('/vehicles', [VehicleController::class, 'index'], ['require_auth']);
$router->post('/vehicles', [VehicleController::class, 'store'], ['require_auth']);
$router->post('/vehicles/{id}/variants/{vid}/delete', [VehicleController::class, 'destroyVariant'], ['require_auth']);
$router->post('/vehicles/{id}/variants/{vid}', [VehicleController::class, 'updateVariant'], ['require_auth']);
$router->post('/vehicles/{id}/variants', [VehicleController::class, 'addVariant'], ['require_auth']);
$router->post('/vehicles/{id}/images/{imageId}/delete', [VehicleController::class, 'destroyImage'], ['require_auth']);
$router->post('/vehicles/{id}/images', [VehicleController::class, 'uploadImage'], ['require_auth']);
$router->post('/vehicles/{id}/delete', [VehicleController::class, 'destroy'], ['require_auth']);
$router->post('/vehicles/{id}', [VehicleController::class, 'update'], ['require_auth']);
$router->get('/vehicles/{id}', [VehicleController::class, 'show'], ['require_auth']);

// Orders
$router->get('/orders', [OrderController::class, 'index'], ['require_auth']);
$router->get('/orders/create', [OrderController::class, 'create'], ['require_auth']);
$router->post('/orders', [OrderController::class, 'store'], ['require_auth']);
$router->get('/orders/{id}', [OrderController::class, 'show'], ['require_auth']);
$router->post('/orders/{id}/status', [OrderController::class, 'updateStatus'], ['require_auth']);
$router->get('/orders/{id}/invoice/pdf', [OrderController::class, 'invoicePdf'], ['require_auth']);
$router->get('/orders/{id}/print', [OrderController::class, 'print'], ['require_auth']);

// Payments
$router->get('/payments', [PaymentController::class, 'index'], ['require_auth']);
$router->post('/payments', [PaymentController::class, 'store'], ['require_auth']);

// Billing
$router->get('/billing', [BillingController::class, 'index'], ['require_auth']);
$router->post('/billing/{id}', [BillingController::class, 'update'], ['require_auth']);
$router->get('/billing/{id}/preview', [BillingController::class, 'preview'], ['require_auth']);
$router->get('/billing/{id}/pdf', [BillingController::class, 'pdf'], ['require_auth']);
$router->get('/billing/{id}', [BillingController::class, 'show'], ['require_auth']);

// Inventory
$router->get('/inventory', [InventoryController::class, 'index'], ['require_auth']);
$router->post('/inventory/adjust', [InventoryController::class, 'adjust'], ['require_auth']);
$router->post('/inventory/transfer', [InventoryController::class, 'transfer'], ['require_auth']);
$router->post('/inventory/warehouses', [InventoryController::class, 'storeWarehouse'], ['require_auth']);
$router->post('/inventory/warehouses/{id}', [InventoryController::class, 'updateWarehouse'], ['require_auth']);
$router->post('/inventory/warehouses/{id}/delete', [InventoryController::class, 'deleteWarehouse'], ['require_auth']);

// Leads
$router->get('/leads', [LeadController::class, 'index'], ['require_auth']);
$router->post('/leads', [LeadController::class, 'store'], ['require_auth']);
$router->post('/leads/{id}/status', [LeadController::class, 'updateStatus'], ['require_auth']);
$router->post('/leads/{id}/followups', [LeadController::class, 'addFollowup'], ['require_auth']);

// Services
$router->get('/services', [ServiceController::class, 'index'], ['require_auth']);
$router->post('/services', [ServiceController::class, 'store'], ['require_auth']);
$router->post('/services/technicians', [ServiceController::class, 'technicians'], ['require_auth']);
$router->get('/services/{id}', [ServiceController::class, 'show'], ['require_auth']);
$router->post('/services/{id}/job-cards', [ServiceController::class, 'createJobCard'], ['require_auth']);
$router->post('/job-cards/{id}', [ServiceController::class, 'updateJobCard'], ['require_auth']);

// Spare parts
$router->get('/spare-parts', [SparePartController::class, 'index'], ['require_auth']);
$router->post('/spare-parts', [SparePartController::class, 'store'], ['require_auth']);
$router->post('/spare-parts/usage', [SparePartController::class, 'usage'], ['require_auth']);
$router->post('/spare-parts/{id}', [SparePartController::class, 'update'], ['require_auth']);
$router->post('/spare-parts/{id}/delete', [SparePartController::class, 'destroy'], ['require_auth']);

// HR
$router->get('/hr', [HrController::class, 'index'], ['require_auth']);
$router->post('/hr/employees', [HrController::class, 'storeEmployee'], ['require_auth']);
$router->post('/hr/employees/{id}', [HrController::class, 'updateEmployee'], ['require_auth']);
$router->post('/hr/employees/{id}/delete', [HrController::class, 'deleteEmployee'], ['require_auth']);
$router->post('/hr/salaries', [HrController::class, 'storeSalary'], ['require_auth']);
$router->post('/hr/salaries/{id}/delete', [HrController::class, 'deleteSalary'], ['require_auth']);

// Partners
$router->get('/partners', [PartnerController::class, 'index'], ['require_auth']);
$router->post('/partners', [PartnerController::class, 'store'], ['require_auth']);
$router->post('/partners/{id}', [PartnerController::class, 'update'], ['require_auth']);
$router->post('/partners/{id}/delete', [PartnerController::class, 'destroy'], ['require_auth']);
$router->post('/partner-transactions', [PartnerController::class, 'storeTransaction'], ['require_auth']);
$router->post('/partner-transactions/{id}', [PartnerController::class, 'updateTransaction'], ['require_auth']);
$router->post('/partner-transactions/{id}/delete', [PartnerController::class, 'deleteTransaction'], ['require_auth']);

// Expenses
$router->get('/expenses', [ExpenseController::class, 'index'], ['require_auth']);
$router->post('/expenses', [ExpenseController::class, 'store'], ['require_auth']);
$router->get('/expenses/{id}', [ExpenseController::class, 'show'], ['require_auth']);
$router->post('/expenses/categories', [ExpenseController::class, 'storeCategory'], ['require_auth']);
$router->post('/expenses/categories/{id}', [ExpenseController::class, 'updateCategory'], ['require_auth']);
$router->post('/expenses/categories/{id}/delete', [ExpenseController::class, 'deleteCategory'], ['require_auth']);
$router->post('/expenses/{id}', [ExpenseController::class, 'update'], ['require_auth']);
$router->post('/expenses/{id}/delete', [ExpenseController::class, 'destroy'], ['require_auth']);

// Finance
$router->get('/finance', [FinanceController::class, 'index'], ['require_auth']);
$router->post('/finance/bank-accounts', [FinanceController::class, 'storeAccount'], ['require_auth']);
$router->post('/finance/bank-accounts/{id}', [FinanceController::class, 'updateAccount'], ['require_auth']);
$router->post('/finance/bank-accounts/{id}/delete', [FinanceController::class, 'deleteAccount'], ['require_auth']);
$router->post('/finance/loans', [FinanceController::class, 'storeLoan'], ['require_auth']);
$router->post('/finance/loans/{id}', [FinanceController::class, 'updateLoan'], ['require_auth']);
$router->post('/finance/loans/{id}/delete', [FinanceController::class, 'deleteLoan'], ['require_auth']);

// Reports
$router->get('/reports', [ReportController::class, 'index'], ['require_auth']);
$router->get('/reports/export/{type}', [ReportController::class, 'export'], ['require_auth']);

// Admin
$router->get('/admin', [AdminController::class, 'index'], ['require_auth']);
$router->post('/admin/users', [AdminController::class, 'storeUser'], ['require_auth']);
$router->post('/admin/users/{id}', [AdminController::class, 'updateUser'], ['require_auth']);
$router->post('/admin/users/{id}/toggle', [AdminController::class, 'toggleUser'], ['require_auth']);
$router->post('/admin/roles/{id}/permissions', [AdminController::class, 'updateRolePermissions'], ['require_auth']);
$router->post('/admin/settings/{key}', [AdminController::class, 'updateSetting'], ['require_auth']);

return $router;
