<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\BillPdfService;
use App\Services\BankTransactionService;
use App\Services\OrderService;
use RuntimeException;

class OrderController extends Controller
{
    public function index(): void
    {
        require_permission('view_orders');
        $orderType = $this->input('order_type');
        $productType = $this->input('product_type');
        $status = $this->input('status');
        $page = max(1, (int)($this->input('page') ?: 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];

        if (Auth::role() === 'dealer') {
            $where[] = 'o.dealer_id = ?';
            $params[] = Auth::dealerId();
        } elseif ($orderType !== '') {
            $where[] = 'o.order_type = ?';
            $params[] = $orderType;
        }

        if ($productType !== '' && in_array($productType, ['vehicle', 'spare_part'], true)) {
            $where[] = 'o.product_type = ?';
            $params[] = $productType;
        }

        if ($status !== '') {
            $where[] = 'o.status = ?';
            $params[] = $status;
        }

        $sqlWhere = implode(' AND ', $where);
        $count = $this->db()->prepare("SELECT COUNT(*) FROM orders o WHERE {$sqlWhere}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $stmt = $this->db()->prepare(
            "SELECT o.*, d.business_name
             FROM orders o
             LEFT JOIN dealers d ON d.id = o.dealer_id
             WHERE {$sqlWhere}
             ORDER BY o.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        $dealers = [];
        if (can('manage_orders')) {
            $dealers = $this->db()->query(
                "SELECT id, business_name, dealer_code FROM dealers WHERE status = 'approved' ORDER BY business_name"
            )->fetchAll();
        }

        $this->view('orders/index', [
            'title' => 'Sell Orders',
            'orders' => $stmt->fetchAll(),
            'orderType' => $orderType,
            'productType' => $productType,
            'status' => $status,
            'page' => $page,
            'totalPages' => max(1, (int)ceil($total / $perPage)),
            'dealers' => $dealers,
            'canManage' => can('manage_orders'),
            'isAdmin' => Auth::role() === 'super_admin',
            'successOrder' => $_SESSION['last_order'] ?? null,
        ]);
        unset($_SESSION['last_order']);
    }

    public function create(): void
    {
        require_permission('manage_orders');
        $productType = $this->input('product');
        if (!in_array($productType, ['vehicle', 'spare_part'], true)) {
            $productType = 'vehicle';
        }
        $dealers = $this->db()->query(
            "SELECT id, business_name, dealer_code FROM dealers WHERE status = 'approved' ORDER BY business_name"
        )->fetchAll();
        $variants = $this->db()->query(
            "SELECT vv.id, vv.name, vv.sku, vv.color, vv.price, vv.battery_type, vv.battery_spec,
                    v.name AS vehicle_name, c.name AS category_name
             FROM vehicle_variants vv
             JOIN vehicles v ON v.id = vv.vehicle_id
             JOIN vehicle_categories c ON c.id = v.category_id
             WHERE vv.is_active = 1 AND v.is_active = 1
             ORDER BY v.name, vv.name"
        )->fetchAll();
        $spareParts = $this->db()->query(
            "SELECT sp.id, sp.name, sp.part_number, sp.unit_price, sp.quantity_in_stock,
                    sc.name AS category_name
             FROM spare_parts sp
             JOIN spare_categories sc ON sc.id = sp.category_id
             WHERE sp.is_active = 1
             ORDER BY sc.name, sp.name"
        )->fetchAll();

        $this->view('orders/create', [
            'title' => $productType === 'spare_part' ? 'Create Spare Parts Sell Order' : 'Create Sell Order',
            'dealers' => $dealers,
            'variants' => $variants,
            'spareParts' => $spareParts,
            'bankAccounts' => BankTransactionService::loadActiveAccounts($this->db()),
            'productType' => $productType,
            'isAdmin' => Auth::role() === 'super_admin',
        ]);
    }

    public function store(): void
    {
        require_permission('manage_orders');
        $this->validateCsrf();

        $items = [];
        $productType = $this->input('product_type') ?: 'vehicle';
        if ($productType === 'spare_part') {
            $spareIds = $_POST['spare_part_id'] ?? [];
            $qtys = $_POST['quantity'] ?? [];
            if (is_array($spareIds)) {
                foreach ($spareIds as $i => $sid) {
                    if ((int)$sid > 0) {
                        $items[] = [
                            'spare_part_id' => (int)$sid,
                            'quantity' => (int)($qtys[$i] ?? 1),
                        ];
                    }
                }
            }
        } else {
            $variantIds = $_POST['variant_id'] ?? [];
            $qtys = $_POST['quantity'] ?? [];
            if (is_array($variantIds)) {
                foreach ($variantIds as $i => $vid) {
                    if ((int)$vid > 0) {
                        $items[] = [
                            'variant_id' => (int)$vid,
                            'quantity' => (int)($qtys[$i] ?? 1),
                        ];
                    }
                }
            }
        }

        $payload = [
            'product_type' => $productType,
            'order_type' => $this->input('order_type'),
            'dealer_id' => $this->input('dealer_id'),
            'booking_no' => $this->input('booking_no'),
            'customer_name' => $this->input('customer_name'),
            'customer_phone' => trim((string)$this->input('customer_phone')) !== ''
                ? format_phone($this->input('customer_phone'))
                : null,
            'customer_email' => $this->input('customer_email'),
            'customer_address' => $this->input('customer_address'),
            'customer_aadhaar' => trim((string)$this->input('customer_aadhaar')) !== ''
                ? format_aadhar($this->input('customer_aadhaar'))
                : null,
            'customer_pan' => $this->input('customer_pan'),
            'chassis_no' => $this->input('chassis_no'),
            'motor_no' => $this->input('motor_no'),
            'battery_capacity' => $this->input('battery_capacity'),
            'battery_no' => $this->input('battery_no'),
            'controller_no' => $this->input('controller_no'),
            'charger_no' => $this->input('charger_no'),
            'motor_warranty' => $this->input('motor_warranty'),
            'battery_warranty' => $this->input('battery_warranty'),
            'controller_warranty' => $this->input('controller_warranty'),
            'charger_warranty' => $this->input('charger_warranty'),
            'hp_name' => $this->input('hp_name'),
            'color' => $this->input('color'),
            'vehicle_model_type' => $this->input('vehicle_model_type'),
            'pm_drive_incentive' => $this->input('pm_drive_incentive'),
            'state_subsidy' => $this->input('state_subsidy'),
            'loan_amount' => $this->input('loan_amount'),
            'discount_amount' => $this->input('discount_amount'),
            'payment_status' => $this->input('payment_status'),
            'amount_paid' => $this->input('amount_paid'),
            'cgst_rate' => $this->input('cgst_rate'),
            'sgst_rate' => $this->input('sgst_rate'),
            'sale_date' => $this->input('sale_date'),
            'paid_cash' => isset($_POST['paid_cash']) ? 1 : 0,
            'paid_cheque' => isset($_POST['paid_cheque']) ? 1 : 0,
            'delivery_address' => $this->input('delivery_address'),
            'notes' => $this->input('notes'),
            'expected_delivery_date' => $this->input('expected_delivery_date'),
            'billing_location' => $this->input('billing_location'),
            'affect_bank' => isset($_POST['affect_bank']) ? 1 : 0,
            'bank_account_id' => $this->input('bank_account_id'),
            'items' => $items,
        ];

        try {
            $result = OrderService::create($payload, (int)Auth::id());
            flash('success', 'Sell order ' . $result['order_number'] . ' created with bill ' . $result['bill_number']);
            $this->redirect('/orders/' . $result['order_id']);
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            $this->redirect('/orders/create?product=' . urlencode($productType === 'spare_part' ? 'spare_part' : 'vehicle'));
        } catch (\Throwable $e) {
            flash('error', env('APP_DEBUG') === 'true' ? $e->getMessage() : 'Failed to create sell order.');
            $this->redirect('/orders/create?product=' . urlencode($productType === 'spare_part' ? 'spare_part' : 'vehicle'));
        }
    }

    public function show(string $id): void
    {
        require_permission('view_orders');
        $orderId = (int)$id;
        $stmt = $this->db()->prepare(
            'SELECT o.*, d.business_name, d.dealer_code,
                    ba.account_name AS bank_account_name, ba.bank_name
             FROM orders o
             LEFT JOIN dealers d ON d.id = o.dealer_id
             LEFT JOIN bank_accounts ba ON ba.id = o.bank_account_id
             WHERE o.id = ?'
        );
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            flash('error', 'Sell order not found.');
            $this->redirect('/orders');
        }
        if (Auth::role() === 'dealer' && (int)$order['dealer_id'] !== Auth::dealerId()) {
            flash('error', 'Unauthorized.');
            $this->redirect('/orders');
        }

        $items = $this->db()->prepare(
            'SELECT oi.*, v.name AS vehicle_name, vv.name AS variant_name, vv.sku, vv.color,
                    sp.name AS spare_part_name, sp.part_number, sc.name AS spare_category_name
             FROM order_items oi
             LEFT JOIN vehicles v ON v.id = oi.vehicle_id
             LEFT JOIN vehicle_variants vv ON vv.id = oi.variant_id
             LEFT JOIN spare_parts sp ON sp.id = oi.spare_part_id
             LEFT JOIN spare_categories sc ON sc.id = sp.category_id
             WHERE oi.order_id = ?'
        );
        $items->execute([$orderId]);

        $history = $this->db()->prepare(
            'SELECT h.*, u.first_name, u.last_name FROM order_status_history h
             JOIN users u ON u.id = h.changed_by WHERE h.order_id = ? ORDER BY h.created_at'
        );
        $history->execute([$orderId]);

        $bill = $this->db()->prepare('SELECT * FROM bills WHERE order_id = ? LIMIT 1');
        $bill->execute([$orderId]);
        $billRow = $bill->fetch() ?: null;

        $this->view('orders/show', [
            'title' => $order['order_number'],
            'order' => $order,
            'items' => $items->fetchAll(),
            'history' => $history->fetchAll(),
            'bill' => $billRow,
            'canManage' => can('manage_orders') || can('approve_orders'),
        ]);
    }

    public function updateStatus(string $id): void
    {
        require_permission('manage_orders');
        $this->validateCsrf();
        try {
            OrderService::updateStatus((int)$id, $this->input('status'), (int)Auth::id(), $this->input('notes'));
            flash('success', 'Sell order status updated.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        $this->redirect('/orders/' . (int)$id);
    }

    public function print(string $id): void
    {
        $this->outputTaxInvoice((int)$id, false);
    }

    public function invoicePdf(string $id): void
    {
        $this->outputTaxInvoice((int)$id, true);
    }

    private function outputTaxInvoice(int $orderId, bool $asPdf): void
    {
        require_permission('view_orders');

        $stmt = $this->db()->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            flash('error', 'Sell order not found.');
            $this->redirect('/orders');
        }
        if (Auth::role() === 'dealer' && (int)$order['dealer_id'] !== Auth::dealerId()) {
            flash('error', 'Unauthorized.');
            $this->redirect('/orders');
        }

        $billStmt = $this->db()->prepare(
            'SELECT * FROM bills WHERE order_id = ? LIMIT 1'
        );
        $billStmt->execute([$orderId]);
        $bill = $billStmt->fetch();
        if (!$bill) {
            flash('error', 'Tax invoice not found for this sell order.');
            $this->redirect('/orders/' . $orderId);
        }
        $bill['order_type'] = $order['order_type'] ?? null;

        $items = $this->db()->prepare('SELECT * FROM bill_items WHERE bill_id = ? ORDER BY id ASC');
        $items->execute([(int)$bill['id']]);
        $lineItems = $items->fetchAll();

        if ($asPdf) {
            BillPdfService::outputPdf($bill, $lineItems);
        }

        echo BillPdfService::renderHtml($bill, $lineItems);
        exit;
    }
}
