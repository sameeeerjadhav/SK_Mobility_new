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
                bill_number, bill_type, company_name, company_address, company_phone, company_email,
                company_gstin, company_state_code, brand_name,
                customer_name, customer_phone, customer_address,
                vehicle_model, chassis_no, motor_no, registration_no,
                warranty_start, warranty_end, warranty_period, notes, total_amount, created_by
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?)'
        )->execute([
            $billNumber, 'warranty',
            setting('company_name'), setting('company_address'), setting('company_phone'), setting('company_email'),
            setting('company_gstin'), setting('company_state_code'), setting('brand_name'),
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
