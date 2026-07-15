<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Services\BillPdfService;

class BillingController extends Controller
{
    public function index(): void
    {
        require_permission('view_billing');
        $type = $this->input('bill_type');
        $where = ['1=1'];
        $params = [];
        if ($type !== '') {
            $where[] = 'bill_type = ?';
            $params[] = $type;
        }
        $sqlWhere = implode(' AND ', $where);

        $counts = [
            'all' => (int)$this->db()->query('SELECT COUNT(*) FROM bills')->fetchColumn(),
            'vehicle' => (int)$this->db()->query("SELECT COUNT(*) FROM bills WHERE bill_type='vehicle'")->fetchColumn(),
            'warranty' => (int)$this->db()->query("SELECT COUNT(*) FROM bills WHERE bill_type='warranty'")->fetchColumn(),
        ];

        $stmt = $this->db()->prepare(
            "SELECT * FROM bills WHERE {$sqlWhere} ORDER BY created_at DESC LIMIT 100"
        );
        $stmt->execute($params);

        $this->view('billing/index', [
            'title' => 'Billing',
            'bills' => $stmt->fetchAll(),
            'billType' => $type,
            'counts' => $counts,
            'canManage' => can('manage_billing'),
        ]);
    }

    public function show(string $id): void
    {
        require_permission('view_billing');
        [$bill, $items] = $this->loadBill((int)$id);
        $this->view('billing/show', [
            'title' => $bill['bill_number'],
            'bill' => $bill,
            'items' => $items,
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

    public function createWarranty(): void
    {
        require_role('super_admin');
        $this->validateCsrf();

        $billNumber = next_code('WAR', 'bills', 'bill_number');
        $start = $this->input('warranty_start') ?: date('Y-m-d');
        $period = $this->input('warranty_period') ?: '24 months';
        $end = date('Y-m-d', strtotime($start . ' +24 months'));

        $this->db()->prepare(
            'INSERT INTO bills (
                bill_number, bill_type, company_name, company_address, company_branch_address, company_phone, company_email,
                company_gstin, company_state, company_state_code, brand_name,
                customer_name, customer_phone, customer_address,
                vehicle_model, chassis_no, motor_no, registration_no,
                warranty_start, warranty_end, warranty_period, notes, total_amount, created_by
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?)'
        )->execute([
            $billNumber, 'warranty',
            setting('company_name'), setting('company_address'), setting('company_branch_address'),
            setting('company_phone'), setting('company_email'),
            setting('company_gstin'), setting('company_state', 'Maharashtra'), setting('company_state_code'), setting('brand_name'),
            $this->input('customer_name'), $this->input('customer_phone'), $this->input('customer_address'),
            $this->input('vehicle_model'), $this->input('chassis_no'), $this->input('motor_no'),
            $this->input('registration_no'),
            $start, $end, $period, $this->input('notes'), Auth::id(),
        ]);
        $id = (int)$this->db()->lastInsertId();
        Audit::log('create', 'billing', 'bills', $id);
        flash('success', 'Warranty certificate ' . $billNumber . ' created.');
        $this->redirect('/billing/' . $id);
    }

    public function update(string $id): void
    {
        require_permission('manage_billing');
        $this->validateCsrf();
        $billId = (int)$id;

        $stmt = $this->db()->prepare('SELECT * FROM bills WHERE id = ?');
        $stmt->execute([$billId]);
        $bill = $stmt->fetch();
        if (!$bill) {
            flash('error', 'Bill not found.');
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
        $cgst = round($taxable * ((float)$bill['cgst_rate'] / 100), 2);
        $sgst = round($taxable * ((float)$bill['sgst_rate'] / 100), 2);
        $total = round($taxable + $cgst + $sgst, 2);

        $this->db()->prepare(
            'UPDATE bills SET
                booking_no=?, customer_name=?, customer_phone=?, customer_email=?, customer_address=?,
                customer_aadhaar=?, customer_pan=?,
                vehicle_model=?, vehicle_model_type=?, color=?, chassis_no=?, motor_no=?,
                battery_type_no=?, controller_no=?, charger_no=?,
                motor_warranty=?, battery_warranty=?, controller_warranty=?, charger_warranty=?,
                hp_name=?, vehicle_sale_date=?,
                pm_drive_incentive=?, state_subsidy=?, loan_amount=?, discount_amount=?,
                payment_mode=?, total_amount=?
             WHERE id=?'
        )->execute([
            $this->input('booking_no') ?: null,
            $this->input('customer_name'),
            $this->input('customer_phone'),
            $this->input('customer_email'),
            $this->input('customer_address'),
            $this->input('customer_aadhaar'),
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
            $paymentMode, $total, $billId,
        ]);

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
                $lineCgst = round($lineTaxable * ((float)$bill['cgst_rate'] / 100), 2);
                $lineSgst = round($lineTaxable * ((float)$bill['sgst_rate'] / 100), 2);
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
        $stmt = $this->db()->prepare('SELECT * FROM bills WHERE id = ?');
        $stmt->execute([$id]);
        $bill = $stmt->fetch();
        if (!$bill) {
            flash('error', 'Bill not found.');
            $this->redirect('/billing');
        }
        $items = $this->db()->prepare('SELECT * FROM bill_items WHERE bill_id = ?');
        $items->execute([$id]);
        return [$bill, $items->fetchAll()];
    }
}
