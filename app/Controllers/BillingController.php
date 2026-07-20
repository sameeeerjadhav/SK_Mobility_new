<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Services\BillPdfService;
use App\Services\OrderService;

class BillingController extends Controller
{
    public function index(): void
    {
        require_permission('view_billing');

        $orderType = trim((string)$this->input('order_type'));
        $billingLocation = trim((string)$this->input('billing_location'));
        $from = trim((string)$this->input('from'));
        $to = trim((string)$this->input('to'));
        $locationFilterAvailable = $this->billingLocationColumnExists();

        $where = ["b.bill_type IN ('vehicle','spare')"];
        $params = [];
        if ($orderType !== '' && in_array($orderType, ['dealer', 'customer'], true)) {
            $where[] = 'o.order_type = ?';
            $params[] = $orderType;
        }
        if ($locationFilterAvailable && $billingLocation !== '' && in_array($billingLocation, ['kokamthan', 'kopargaon'], true)) {
            $where[] = 'b.billing_location = ?';
            $params[] = $billingLocation;
        } elseif ($billingLocation !== '' && !$locationFilterAvailable) {
            flash('warning', 'Billing location filter needs a database update. Run /install.php?migrate_billing_location=1 once, then try again.');
            $billingLocation = '';
        }
        if ($from !== '') {
            $where[] = 'COALESCE(b.vehicle_sale_date, DATE(b.created_at)) >= ?';
            $params[] = $from;
        }
        if ($to !== '') {
            $where[] = 'COALESCE(b.vehicle_sale_date, DATE(b.created_at)) <= ?';
            $params[] = $to;
        }
        $sqlWhere = implode(' AND ', $where);

        $countStmt = $this->db()->prepare(
            "SELECT COUNT(*) FROM bills b LEFT JOIN orders o ON o.id = b.order_id WHERE {$sqlWhere}"
        );
        $countStmt->execute($params);
        $invoiceCount = (int)$countStmt->fetchColumn();
        $pager = paginate($invoiceCount, max(1, (int)($this->input('page') ?: 1)), 20);

        $totalInvoices = (int)$this->db()->query(
            "SELECT COUNT(*) FROM bills WHERE bill_type IN ('vehicle','spare')"
        )->fetchColumn();

        $stmt = $this->db()->prepare(
            "SELECT b.*, o.order_type
             FROM bills b
             LEFT JOIN orders o ON o.id = b.order_id
             WHERE {$sqlWhere}
             ORDER BY b.created_at DESC
             LIMIT {$pager['per_page']} OFFSET {$pager['offset']}"
        );
        $stmt->execute($params);

        $this->view('billing/index', [
            'title' => 'Tax Invoices',
            'bills' => $stmt->fetchAll(),
            'totalInvoices' => $totalInvoices,
            'invoiceCount' => $invoiceCount,
            'orderType' => $orderType,
            'billingLocation' => $billingLocation,
            'from' => $from,
            'to' => $to,
            'locationFilterAvailable' => $locationFilterAvailable,
            'canManage' => can('manage_billing'),
            'pagination' => $pager,
            'filters' => [
                'order_type' => $orderType,
                'billing_location' => $billingLocation,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    public function show(string $id): void
    {
        require_permission('view_billing');
        [$bill, $items] = $this->loadBill((int)$id);
        $orderType = null;
        if (!empty($bill['order_id'])) {
            $o = $this->db()->prepare('SELECT order_type FROM orders WHERE id = ?');
            $o->execute([(int)$bill['order_id']]);
            $orderType = $o->fetchColumn() ?: null;
        }
        $this->view('billing/show', [
            'title' => $bill['bill_number'],
            'bill' => $bill,
            'items' => $items,
            'orderType' => $orderType,
        ]);
    }

    public function preview(string $id): void
    {
        require_permission('view_billing');
        [$bill, $items] = $this->loadBill((int)$id);
        echo BillPdfService::renderHtml($bill, $items);
        exit;
    }

    public function pdf(string $id): void
    {
        require_permission('view_billing');
        [$bill, $items] = $this->loadBill((int)$id);
        BillPdfService::outputPdf($bill, $items);
    }

    public function update(string $id): void
    {
        require_permission('manage_billing');
        $this->validateCsrf();
        $billId = (int)$id;

        $stmt = $this->db()->prepare('SELECT * FROM bills WHERE id = ?');
        $stmt->execute([$billId]);
        $bill = $stmt->fetch();
        if (!$bill || !in_array($bill['bill_type'] ?? '', ['vehicle', 'spare'], true)) {
            flash('error', 'Tax invoice not found.');
            $this->redirect('/billing');
        }

        $paidCash = isset($_POST['paid_cash']) ? 1 : 0;
        $paidCheque = isset($_POST['paid_cheque']) ? 1 : 0;
        $paymentParts = [];
        if ($paidCash) {
            $paymentParts[] = 'cash';
        }
        if ($paidCheque) {
            $paymentParts[] = 'cheque';
        }
        $paymentMode = $paymentParts ? implode('_', $paymentParts) : null;

        $loan = (float)$this->input('loan_amount');
        $discount = (float)$this->input('discount_amount');
        $pm = (float)$this->input('pm_drive_incentive');
        $state = (float)$this->input('state_subsidy');
        $subtotal = (float)$bill['subtotal'];
        $taxable = max(0, $subtotal - $pm - $state - $discount);
        $billProductType = ($bill['bill_type'] ?? '') === 'spare' ? 'spare_part' : 'vehicle';
        try {
            [$cgstRate, $sgstRate] = OrderService::resolveGstRates([
                'cgst_rate' => $this->input('cgst_rate'),
                'sgst_rate' => $this->input('sgst_rate'),
            ], $billProductType);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            $this->redirect('/billing/' . $billId);
        }
        $taxRate = round($cgstRate + $sgstRate, 2);
        $cgst = round($taxable * ($cgstRate / 100), 2);
        $sgst = round($taxable * ($sgstRate / 100), 2);
        $taxAmount = round($cgst + $sgst, 2);
        $total = round($taxable + $taxAmount, 2);

        try {
            [$paymentStatus, $amountPaid, $amountDue] = OrderService::resolvePaymentAmounts([
                'payment_status' => $this->input('payment_status'),
                'amount_paid' => $this->input('amount_paid'),
            ], $total);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            $this->redirect('/billing/' . $billId);
        }

        $this->db()->prepare(
            'UPDATE bills SET
                booking_no=?, customer_name=?, customer_phone=?, customer_email=?, customer_address=?,
                customer_aadhaar=?, customer_pan=?,
                vehicle_model=?, vehicle_model_type=?, color=?, chassis_no=?, motor_no=?,
                battery_type_no=?, controller_no=?, charger_no=?,
                motor_warranty=?, battery_warranty=?, controller_warranty=?, charger_warranty=?,
                hp_name=?, vehicle_sale_date=?,
                pm_drive_incentive=?, state_subsidy=?, loan_amount=?, discount_amount=?,
                cgst_rate=?, sgst_rate=?, tax_rate=?,
                payment_mode=?, payment_status=?, amount_paid=?, amount_due=?, total_amount=?
             WHERE id=?'
        )->execute([
            $this->input('booking_no') ?: null,
            $this->input('customer_name'),
            trim((string)$this->input('customer_phone')) !== '' ? format_phone($this->input('customer_phone')) : null,
            $this->input('customer_email'),
            $this->input('customer_address'),
            trim((string)$this->input('customer_aadhaar')) !== '' ? format_aadhar($this->input('customer_aadhaar')) : null,
            $this->input('customer_pan'),
            $this->input('vehicle_model'),
            $this->input('vehicle_model_type'),
            $this->input('color'),
            $this->input('chassis_no'),
            $this->input('motor_no'),
            $this->input('battery_type_no'),
            $this->input('controller_no'),
            $this->input('charger_no'),
            $this->input('motor_warranty'),
            $this->input('battery_warranty'),
            $this->input('controller_warranty'),
            $this->input('charger_warranty'),
            $this->input('hp_name'),
            $this->input('vehicle_sale_date') ?: null,
            $pm, $state, $loan, $discount,
            $cgstRate, $sgstRate, $taxRate,
            $paymentMode, $paymentStatus, $amountPaid, $amountDue, $total, $billId,
        ]);

        if (!empty($bill['order_id'])) {
            $this->db()->prepare(
                'UPDATE orders SET
                    pm_drive_incentive=?, state_subsidy=?, loan_amount=?, discount_amount=?,
                    cgst_rate=?, sgst_rate=?, tax_rate=?, tax_amount=?,
                    payment_mode=?, payment_status=?, amount_paid=?, amount_due=?, total_amount=?
                 WHERE id=?'
            )->execute([
                $pm, $state, $loan, $discount,
                $cgstRate, $sgstRate, $taxRate, $taxAmount,
                $paymentMode, $paymentStatus, $amountPaid, $amountDue, $total,
                (int)$bill['order_id'],
            ]);
        }

        // Refresh first bill line tax breakdown when amounts change
        $items = $this->db()->prepare('SELECT * FROM bill_items WHERE bill_id = ? ORDER BY id ASC');
        $items->execute([$billId]);
        $rows = $items->fetchAll();
        if ($rows) {
            $totalDisc = $pm + $state + $discount;
            $upd = $this->db()->prepare(
                'UPDATE bill_items SET discount=?, taxable_amount=?, cgst_amount=?, sgst_amount=?, total_price=? WHERE id=?'
            );
            foreach ($rows as $idx => $row) {
                $lineDisc = $idx === 0 ? $totalDisc : 0.0;
                $lineTaxable = max(0, (float)$row['unit_price'] * (int)$row['quantity'] - $lineDisc);
                $lineCgst = round($lineTaxable * ($cgstRate / 100), 2);
                $lineSgst = round($lineTaxable * ($sgstRate / 100), 2);
                $lineTotal = round($lineTaxable + $lineCgst + $lineSgst, 2);
                $upd->execute([$lineDisc, $lineTaxable, $lineCgst, $lineSgst, $lineTotal, $row['id']]);
            }
        }

        Audit::log('update', 'billing', 'bills', $billId);
        flash('success', 'Tax invoice details saved. Open Preview / Print to see the SAI KUBER format.');
        $this->redirect('/billing/' . $billId);
    }

    private function loadBill(int $id): array
    {
        $stmt = $this->db()->prepare(
            'SELECT b.*, o.order_type FROM bills b LEFT JOIN orders o ON o.id = b.order_id WHERE b.id = ?'
        );
        $stmt->execute([$id]);
        $bill = $stmt->fetch();
        if (!$bill || !in_array($bill['bill_type'] ?? '', ['vehicle', 'spare'], true)) {
            flash('error', 'Tax invoice not found.');
            $this->redirect('/billing');
        }
        $items = $this->db()->prepare('SELECT * FROM bill_items WHERE bill_id = ? ORDER BY id ASC');
        $items->execute([$id]);
        return [$bill, $items->fetchAll()];
    }

    private function billingLocationColumnExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute(['bills', 'billing_location']);
        $exists = (int)$stmt->fetchColumn() > 0;
        return $exists;
    }
}
