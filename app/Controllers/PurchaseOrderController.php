<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Services\PurchaseOrderService;
use RuntimeException;

class PurchaseOrderController extends Controller
{
    private function service(): PurchaseOrderService
    {
        return new PurchaseOrderService($this->db());
    }

    public function index(): void
    {
        require_role('super_admin');

        $status = trim((string)$this->input('status'));
        $supplier = trim((string)$this->input('supplier'));
        $search = trim((string)$this->input('search'));
        $from = $this->input('from');
        $to = $this->input('to');

        $where = ['1=1'];
        $params = [];
        if ($status !== '') {
            $where[] = 'po.status = ?';
            $params[] = $status;
        }
        if ($supplier !== '') {
            $where[] = 'po.supplier_name LIKE ?';
            $params[] = '%' . $supplier . '%';
        }
        if ($search !== '') {
            $where[] = '(po.po_number LIKE ? OR po.supplier_invoice_no LIKE ? OR po.supplier_name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($from) {
            $where[] = 'po.po_date >= ?';
            $params[] = $from;
        }
        if ($to) {
            $where[] = 'po.po_date <= ?';
            $params[] = $to;
        }
        $sqlWhere = implode(' AND ', $where);

        $db = $this->db();
        $stats = [
            'open' => (int)$db->query(
                "SELECT COUNT(*) FROM purchase_orders WHERE status IN ('draft','confirmed','partial')"
            )->fetchColumn(),
            'received_month' => (int)$db->query(
                "SELECT COUNT(*) FROM purchase_orders
                 WHERE status = 'received'
                   AND YEAR(updated_at) = YEAR(CURDATE()) AND MONTH(updated_at) = MONTH(CURDATE())"
            )->fetchColumn(),
            'pending_qty' => (int)$db->query(
                'SELECT COALESCE(SUM(quantity_ordered - quantity_received), 0)
                 FROM purchase_order_items poi
                 JOIN purchase_orders po ON po.id = poi.purchase_order_id
                 WHERE po.status NOT IN (\'received\', \'cancelled\')'
            )->fetchColumn(),
            'month_value' => (float)$db->query(
                "SELECT COALESCE(SUM(total_amount), 0) FROM purchase_orders
                 WHERE po_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
            )->fetchColumn(),
        ];

        $stmt = $db->prepare(
            "SELECT po.*,
                    (SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_id = po.id) AS line_count,
                    (SELECT COALESCE(SUM(quantity_ordered), 0) FROM purchase_order_items WHERE purchase_order_id = po.id) AS total_qty
             FROM purchase_orders po
             WHERE {$sqlWhere}
             ORDER BY po.po_date DESC, po.id DESC
             LIMIT 300"
        );
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        $this->view('purchase-orders/index', [
            'title' => 'Purchase Orders',
            'orders' => $orders,
            'stats' => $stats,
            'status' => $status,
            'supplier' => $supplier,
            'search' => $search,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function create(): void
    {
        require_role('super_admin');

        $this->view('purchase-orders/create', [
            'title' => 'New Purchase Order',
            'variants' => $this->loadVariants(),
            'vehicles' => $this->loadVehicles(),
        ]);
    }

    private function loadVehicles(): array
    {
        return $this->db()->query(
            'SELECT id, name FROM vehicles WHERE is_active = 1 ORDER BY name'
        )->fetchAll();
    }

    private function loadVariants(): array
    {
        return $this->db()->query(
            "SELECT vv.id, vv.name, vv.sku, vv.color, vv.price, vv.battery_type, vv.battery_spec,
                    v.id AS vehicle_id, v.name AS vehicle_name
             FROM vehicle_variants vv
             JOIN vehicles v ON v.id = vv.vehicle_id
             WHERE vv.is_active = 1 AND v.is_active = 1
             ORDER BY v.name, vv.name, vv.color"
        )->fetchAll();
    }

    public function show(string $id): void
    {
        require_role('super_admin');
        $poId = (int)$id;
        $service = $this->service();

        try {
            $po = $service->findPo($poId);
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            $this->redirect('/purchase-orders');
        }

        $items = $service->itemsForPo($poId);

        $receipts = $this->db()->prepare(
            'SELECT r.*, u.first_name, u.last_name
             FROM purchase_order_receipts r
             JOIN users u ON u.id = r.created_by
             WHERE r.purchase_order_id = ?
             ORDER BY r.received_at DESC'
        );
        $receipts->execute([$poId]);
        $receiptRows = $receipts->fetchAll();

        $receiptLines = [];
        if ($receiptRows) {
            $ids = implode(',', array_map('intval', array_column($receiptRows, 'id')));
            $lines = $this->db()->query(
                "SELECT rl.*, w.name AS warehouse_name, poi.description, vv.name AS variant_name, v.name AS vehicle_name
                 FROM purchase_order_receipt_lines rl
                 JOIN warehouses w ON w.id = rl.warehouse_id
                 JOIN purchase_order_items poi ON poi.id = rl.po_item_id
                 JOIN vehicle_variants vv ON vv.id = poi.variant_id
                 JOIN vehicles v ON v.id = poi.vehicle_id
                 WHERE rl.receipt_id IN ({$ids})
                 ORDER BY rl.id"
            )->fetchAll();
            foreach ($lines as $line) {
                $receiptLines[(int)$line['receipt_id']][] = $line;
            }
        }

        $this->view('purchase-orders/show', [
            'title' => $po['po_number'],
            'po' => $po,
            'items' => $items,
            'receipts' => $receiptRows,
            'receiptLines' => $receiptLines,
            'warehouses' => $this->db()->query(
                'SELECT id, name FROM warehouses WHERE is_active = 1 ORDER BY name'
            )->fetchAll(),
        ]);
    }

    public function store(): void
    {
        require_role('super_admin');
        $this->validateCsrf();

        try {
            $payload = $this->validatedHeader();
            $lines = $this->service()->calcLines($this->input('items') ?? []);
            $db = $this->db();
            $db->beginTransaction();

            $poNumber = next_code('PO', 'purchase_orders', 'po_number');
            $db->prepare(
                'INSERT INTO purchase_orders (
                    po_number, supplier_name, po_date, supplier_invoice_no, supplier_invoice_date,
                    status, subtotal, gst_amount, total_amount, notes, created_by
                 ) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $poNumber,
                $payload['supplier_name'],
                $payload['po_date'],
                $payload['supplier_invoice_no'],
                $payload['supplier_invoice_date'],
                'confirmed',
                $lines['subtotal'],
                $lines['gst_amount'],
                $lines['total_amount'],
                $payload['notes'],
                Auth::id(),
            ]);
            $poId = (int)$db->lastInsertId();
            $this->service()->insertItems($poId, $lines['items']);
            $db->commit();

            Audit::log('create', 'purchase_orders', 'purchase_orders', $poId);
            flash('success', 'Purchase order ' . $poNumber . ' created.');
            $this->redirect('/purchase-orders/' . $poId);
        } catch (RuntimeException $e) {
            if ($this->db()->inTransaction()) {
                $this->db()->rollBack();
            }
            flash('error', $e->getMessage());
            $this->redirect('/purchase-orders/create');
        }
    }

    public function receive(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $poId = (int)$id;

        try {
            $allocations = $this->parseAllocations();
            $this->service()->receive($poId, $allocations, trim((string)$this->input('notes')) ?: null);
            Audit::log('update', 'purchase_orders', 'purchase_orders', $poId, null, ['action' => 'receive']);
            flash('success', 'Stock received and added to inventory.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        $this->redirect('/purchase-orders/' . $poId);
    }

    public function cancel(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $poId = (int)$id;

        $po = $this->db()->prepare('SELECT status FROM purchase_orders WHERE id = ?');
        $po->execute([$poId]);
        $status = $po->fetchColumn();
        if (!$status) {
            flash('error', 'Purchase order not found.');
            $this->redirect('/purchase-orders');
        }
        if ($status === 'received') {
            flash('error', 'Cannot cancel a fully received purchase order.');
            $this->redirect('/purchase-orders/' . $poId);
        }
        $recvStmt = $this->db()->prepare(
            'SELECT COALESCE(SUM(quantity_received), 0) FROM purchase_order_items WHERE purchase_order_id = ?'
        );
        $recvStmt->execute([$poId]);
        if ((int)$recvStmt->fetchColumn() > 0) {
            flash('error', 'Cannot cancel — stock has already been received. Adjust inventory manually if needed.');
            $this->redirect('/purchase-orders/' . $poId);
        }

        $this->db()->prepare("UPDATE purchase_orders SET status = 'cancelled' WHERE id = ?")->execute([$poId]);
        Audit::log('update', 'purchase_orders', 'purchase_orders', $poId, null, ['status' => 'cancelled']);
        flash('success', 'Purchase order cancelled.');
        $this->redirect('/purchase-orders');
    }

    /** @return list<array{po_item_id: int, warehouse_id: int, quantity: int}> */
    private function parseAllocations(): array
    {
        $raw = $this->input('allocations');
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'po_item_id' => (int)($row['po_item_id'] ?? 0),
                'warehouse_id' => (int)($row['warehouse_id'] ?? 0),
                'quantity' => (int)($row['quantity'] ?? 0),
            ];
        }
        return $out;
    }

    /** @return array{supplier_name: string, po_date: string, supplier_invoice_no: ?string, supplier_invoice_date: ?string, notes: ?string} */
    private function validatedHeader(): array
    {
        $supplierName = trim((string)$this->input('supplier_name'));
        if ($supplierName === '') {
            throw new RuntimeException('Supplier company name is required.');
        }
        $poDate = trim((string)$this->input('po_date'));
        if ($poDate === '') {
            $poDate = date('Y-m-d');
        }
        return [
            'supplier_name' => $supplierName,
            'po_date' => $poDate,
            'supplier_invoice_no' => trim((string)$this->input('supplier_invoice_no')) ?: null,
            'supplier_invoice_date' => trim((string)$this->input('supplier_invoice_date')) ?: null,
            'notes' => trim((string)$this->input('notes')) ?: null,
        ];
    }
}
