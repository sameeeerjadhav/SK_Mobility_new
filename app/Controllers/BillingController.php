<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Services\BillPdfService;
use App\Services\BankTransactionService;
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
        $order = null;
        if (!empty($bill['order_id'])) {
            $o = $this->db()->prepare(
                'SELECT o.*, ba.account_name AS bank_account_name
                 FROM orders o
                 LEFT JOIN bank_accounts ba ON ba.id = o.bank_account_id
                 WHERE o.id = ?'
            );
            $o->execute([(int)$bill['order_id']]);
            $order = $o->fetch() ?: null;
            $orderType = $order['order_type'] ?? null;
        }
        $productType = ($bill['bill_type'] ?? '') === 'spare' ? 'spare_part' : 'vehicle';
        $source = $order ?: $bill;
        [$paidCash, $paidBank, $paidLoan] = OrderService::parseStoredPaymentBreakdown($source);
        $batteryCapacity = (string)($order['battery_capacity'] ?? '');
        $batteryNo = (string)($order['battery_no'] ?? '');
        if ($batteryCapacity === '' && $batteryNo === '' && !empty($bill['battery_type_no'])) {
            $batteryNo = (string)$bill['battery_type_no'];
        }
        $this->view('billing/show', [
            'title' => $bill['bill_number'],
            'bill' => $bill,
            'items' => $items,
            'orderType' => $orderType,
            'order' => $order,
            'productType' => $productType,
            'paidCash' => $paidCash,
            'paidBank' => $paidBank,
            'paidLoan' => $paidLoan,
            'batteryCapacity' => $batteryCapacity,
            'batteryNo' => $batteryNo,
            'bankAccounts' => can('manage_billing') ? BankTransactionService::loadActiveAccounts($this->db()) : [],
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

        $subtotal = (float)$bill['subtotal'];
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
        $taxable = max(0, $subtotal);
        $cgst = round($taxable * ($cgstRate / 100), 2);
        $sgst = round($taxable * ($sgstRate / 100), 2);
        $taxAmount = round($cgst + $sgst, 2);
        $total = round($taxable + $taxAmount, 2);

        try {
            [$paymentStatus, $amountPaid, $amountDue, $paidCash, $paidBank, $paidLoan] = OrderService::resolvePaymentBreakdown([
                'payment_status' => $this->input('payment_status'),
                'paid_cash_amount' => $this->input('paid_cash_amount'),
                'paid_bank_amount' => $this->input('paid_bank_amount'),
                'paid_loan_amount' => $this->input('paid_loan_amount'),
            ], $total, $subtotal, $taxAmount);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            $this->redirect('/billing/' . $billId);
        }

        $paymentMode = OrderService::formatPaymentMode($paidCash, $paidBank, $paidLoan);
        $loanAmount = $paidLoan;
        $bankAccountId = (int)($this->input('bank_account_id') ?? 0);
        $affectBank = $paidBank > 0 && $bankAccountId > 0;
        if ($paidBank > 0 && $bankAccountId <= 0) {
            flash('error', 'Select a bank account for the online/bank payment amount.');
            $this->redirect('/billing/' . $billId);
        }

        $billingLocation = strtolower(trim((string)$this->input('billing_location')));
        if (!in_array($billingLocation, ['kokamthan', 'kopargaon'], true)) {
            $billingLocation = $bill['billing_location'] ?? 'kokamthan';
        }

        $batteryCapacity = trim((string)$this->input('battery_capacity'));
        $batteryNo = trim((string)$this->input('battery_no'));
        $batteryTypeNo = trim(implode(' ', array_filter([$batteryCapacity, $batteryNo])));

        $this->db()->prepare(
            'UPDATE bills SET
                booking_no=?, billing_location=?, customer_name=?, customer_phone=?, customer_email=?, customer_address=?,
                customer_aadhaar=?, customer_pan=?,
                vehicle_model=?, vehicle_model_type=?, color=?, chassis_no=?, motor_no=?,
                battery_type_no=?, controller_no=?, charger_no=?,
                motor_warranty=?, battery_warranty=?, controller_warranty=?, charger_warranty=?,
                hp_name=?, vehicle_sale_date=?,
                pm_drive_incentive=0, state_subsidy=0, loan_amount=?, discount_amount=0,
                cgst_rate=?, sgst_rate=?, tax_rate=?,
                payment_mode=?, payment_status=?, amount_paid=?, amount_due=?, total_amount=?
             WHERE id=?'
        )->execute([
            $this->input('booking_no') ?: null,
            $billingLocation,
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
            $batteryTypeNo !== '' ? $batteryTypeNo : null,
            $this->input('controller_no'),
            $this->input('charger_no'),
            $this->input('motor_warranty'),
            $this->input('battery_warranty'),
            $this->input('controller_warranty'),
            $this->input('charger_warranty'),
            $this->input('hp_name'),
            $this->input('vehicle_sale_date') ?: null,
            $loanAmount,
            $cgstRate, $sgstRate, $taxRate,
            $paymentMode, $paymentStatus, $amountPaid, $amountDue, $total, $billId,
        ]);

        if (!empty($bill['order_id'])) {
            $this->db()->prepare(
                'UPDATE orders SET
                    booking_no=?, billing_location=?, customer_name=?, customer_phone=?, customer_email=?, customer_address=?,
                    customer_aadhaar=?, customer_pan=?,
                    chassis_no=?, motor_no=?, battery_capacity=?, battery_no=?,
                    controller_no=?, charger_no=?,
                    motor_warranty=?, battery_warranty=?, controller_warranty=?, charger_warranty=?,
                    hp_name=?, color=?, vehicle_model_type=?,
                    pm_drive_incentive=0, state_subsidy=0, loan_amount=?, discount_amount=0,
                    cgst_rate=?, sgst_rate=?, tax_rate=?, tax_amount=?,
                    payment_mode=?, payment_status=?, amount_paid=?, amount_due=?, total_amount=?,
                    bank_account_id=?, affect_bank_balance=?, sale_date=?
                 WHERE id=?'
            )->execute([
                $this->input('booking_no') ?: null,
                $billingLocation,
                $this->input('customer_name'),
                trim((string)$this->input('customer_phone')) !== '' ? format_phone($this->input('customer_phone')) : null,
                $this->input('customer_email'),
                $this->input('customer_address'),
                trim((string)$this->input('customer_aadhaar')) !== '' ? format_aadhar($this->input('customer_aadhaar')) : null,
                $this->input('customer_pan'),
                $this->input('chassis_no'),
                $this->input('motor_no'),
                $batteryCapacity ?: null,
                $batteryNo ?: null,
                $this->input('controller_no'),
                $this->input('charger_no'),
                $this->input('motor_warranty'),
                $this->input('battery_warranty'),
                $this->input('controller_warranty'),
                $this->input('charger_warranty'),
                $this->input('hp_name'),
                $this->input('color'),
                $this->input('vehicle_model_type'),
                $loanAmount,
                $cgstRate, $sgstRate, $taxRate, $taxAmount,
                $paymentMode, $paymentStatus, $amountPaid, $amountDue, $total,
                $bankAccountId ?: null, $affectBank ? 1 : 0,
                $this->input('vehicle_sale_date') ?: null,
                (int)$bill['order_id'],
            ]);
        }

        $items = $this->db()->prepare('SELECT * FROM bill_items WHERE bill_id = ? ORDER BY id ASC');
        $items->execute([$billId]);
        $rows = $items->fetchAll();
        if ($rows) {
            $upd = $this->db()->prepare(
                'UPDATE bill_items SET discount=0, taxable_amount=?, cgst_amount=?, sgst_amount=?, total_price=? WHERE id=?'
            );
            foreach ($rows as $row) {
                $lineTaxable = max(0, (float)$row['unit_price'] * (int)$row['quantity']);
                $lineCgst = round($lineTaxable * ($cgstRate / 100), 2);
                $lineSgst = round($lineTaxable * ($sgstRate / 100), 2);
                $lineTotal = round($lineTaxable + $lineCgst + $lineSgst, 2);
                $upd->execute([$lineTaxable, $lineCgst, $lineSgst, $lineTotal, $row['id']]);
            }
        }

        Audit::log('update', 'billing', 'bills', $billId);
        flash('success', 'Sell order / tax invoice details saved.');
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
