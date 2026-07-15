<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\OrderService;
use RuntimeException;

class OrderController extends Controller
{
    public function index(): void
    {
        require_permission('view_orders');
        $orderType = $this->input('order_type');
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
        $variants = [];
        if (can('manage_orders')) {
            $dealers = $this->db()->query(
                "SELECT id, business_name, dealer_code FROM dealers WHERE status = 'approved' ORDER BY business_name"
            )->fetchAll();
            $variants = $this->db()->query(
                "SELECT vv.id, vv.name, vv.sku, vv.color, vv.price, v.name AS vehicle_name
                 FROM vehicle_variants vv
                 JOIN vehicles v ON v.id = vv.vehicle_id
                 WHERE vv.is_active = 1 AND v.is_active = 1
                 ORDER BY v.name, vv.name"
            )->fetchAll();
        }

        $this->view('orders/index', [
            'title' => 'Orders',
            'orders' => $stmt->fetchAll(),
            'orderType' => $orderType,
            'status' => $status,
            'page' => $page,
            'totalPages' => max(1, (int)ceil($total / $perPage)),
            'dealers' => $dealers,
            'variants' => $variants,
            'canManage' => can('manage_orders'),
            'isAdmin' => Auth::role() === 'super_admin',
            'successOrder' => $_SESSION['last_order'] ?? null,
        ]);
        unset($_SESSION['last_order']);
    }

    public function store(): void
    {
        require_permission('manage_orders');
        $this->validateCsrf();

        $items = [];
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

        $payload = [
            'order_type' => $this->input('order_type'),
            'dealer_id' => $this->input('dealer_id'),
            'customer_name' => $this->input('customer_name'),
            'customer_phone' => $this->input('customer_phone'),
            'customer_email' => $this->input('customer_email'),
            'customer_address' => $this->input('customer_address'),
            'customer_aadhaar' => $this->input('customer_aadhaar'),
            'customer_pan' => $this->input('customer_pan'),
            'chassis_no' => $this->input('chassis_no'),
            'motor_no' => $this->input('motor_no'),
            'battery_capacity' => $this->input('battery_capacity'),
            'color' => $this->input('color'),
            'pm_drive_incentive' => $this->input('pm_drive_incentive'),
            'state_subsidy' => $this->input('state_subsidy'),
            'delivery_address' => $this->input('delivery_address'),
            'notes' => $this->input('notes'),
            'expected_delivery_date' => $this->input('expected_delivery_date'),
            'items' => $items,
        ];

        try {
            $result = OrderService::create($payload, (int)Auth::id());
            $_SESSION['last_order'] = $result;
            flash('success', 'Order ' . $result['order_number'] . ' created with bill ' . $result['bill_number']);
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            flash('error', 'Failed to create order.');
            if (env('APP_DEBUG') === 'true') {
                flash('error', $e->getMessage());
            }
        }
        $this->redirect('/orders');
    }

    public function show(string $id): void
    {
        require_permission('view_orders');
        $orderId = (int)$id;
        $stmt = $this->db()->prepare(
            'SELECT o.*, d.business_name, d.dealer_code
             FROM orders o LEFT JOIN dealers d ON d.id = o.dealer_id WHERE o.id = ?'
        );
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            flash('error', 'Order not found.');
            $this->redirect('/orders');
        }
        if (Auth::role() === 'dealer' && (int)$order['dealer_id'] !== Auth::dealerId()) {
            flash('error', 'Unauthorized.');
            $this->redirect('/orders');
        }

        $items = $this->db()->prepare(
            'SELECT oi.*, v.name AS vehicle_name, vv.name AS variant_name, vv.sku, vv.color
             FROM order_items oi
             JOIN vehicles v ON v.id = oi.vehicle_id
             JOIN vehicle_variants vv ON vv.id = oi.variant_id
             WHERE oi.order_id = ?'
        );
        $items->execute([$orderId]);

        $history = $this->db()->prepare(
            'SELECT h.*, u.first_name, u.last_name FROM order_status_history h
             JOIN users u ON u.id = h.changed_by WHERE h.order_id = ? ORDER BY h.created_at'
        );
        $history->execute([$orderId]);

        $bill = $this->db()->prepare('SELECT id, bill_number FROM bills WHERE order_id = ? LIMIT 1');
        $bill->execute([$orderId]);

        $this->view('orders/show', [
            'title' => $order['order_number'],
            'order' => $order,
            'items' => $items->fetchAll(),
            'history' => $history->fetchAll(),
            'bill' => $bill->fetch() ?: null,
            'canManage' => can('manage_orders') || can('approve_orders'),
        ]);
    }

    public function updateStatus(string $id): void
    {
        require_permission('manage_orders');
        $this->validateCsrf();
        try {
            OrderService::updateStatus((int)$id, $this->input('status'), (int)Auth::id(), $this->input('notes'));
            flash('success', 'Order status updated.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        $this->redirect('/orders/' . (int)$id);
    }

    public function print(string $id): void
    {
        require_permission('view_orders');
        $orderId = (int)$id;
        $stmt = $this->db()->prepare(
            'SELECT o.*, d.business_name FROM orders o LEFT JOIN dealers d ON d.id = o.dealer_id WHERE o.id = ?'
        );
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            exit('Not found');
        }
        $items = $this->db()->prepare(
            'SELECT oi.*, v.name AS vehicle_name, vv.name AS variant_name
             FROM order_items oi
             JOIN vehicles v ON v.id = oi.vehicle_id
             JOIN vehicle_variants vv ON vv.id = oi.variant_id
             WHERE oi.order_id = ?'
        );
        $items->execute([$orderId]);
        $this->view('orders/print', [
            'title' => 'Print ' . $order['order_number'],
            'order' => $order,
            'items' => $items->fetchAll(),
        ], 'print');
    }
}
