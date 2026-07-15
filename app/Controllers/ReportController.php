<?php

namespace App\Controllers;

use App\Core\Controller;

class ReportController extends Controller
{
    public function index(): void
    {
        require_permission('view_reports');
        $from = $this->input('from') ?: date('Y-m-01');
        $to = $this->input('to') ?: date('Y-m-d');
        $this->view('reports/index', [
            'title' => 'Reports',
            'from' => $from,
            'to' => $to,
            'previews' => [
                'sales' => $this->fetchReport('sales', $from, $to, 6),
                'revenue' => $this->fetchReport('revenue', $from, $to, 6),
                'inventory' => $this->fetchReport('inventory', $from, $to, 6),
                'leads' => $this->fetchReport('leads', $from, $to, 6),
                'dealers' => $this->fetchReport('dealers', $from, $to, 6),
            ],
            'canExport' => can('export_reports'),
        ]);
    }

    public function export(string $type): void
    {
        require_permission('export_reports');
        $from = $this->input('from') ?: date('Y-m-01');
        $to = $this->input('to') ?: date('Y-m-d');
        $rows = $this->fetchReport($type, $from, $to, 5000);
        if (!$rows) {
            flash('error', 'No data for this report.');
            $this->redirect('/reports');
        }

        $filename = "{$type}_{$from}_{$to}.csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    private function fetchReport(string $type, string $from, string $to, int $limit): array
    {
        $db = $this->db();
        $limit = max(1, min(5000, $limit));

        switch ($type) {
            case 'sales':
                $stmt = $db->prepare(
                    "SELECT order_number, order_type, total_amount, status, DATE(created_at) AS order_date
                     FROM orders WHERE DATE(created_at) BETWEEN ? AND ?
                     ORDER BY created_at DESC LIMIT {$limit}"
                );
                $stmt->execute([$from, $to]);
                return $stmt->fetchAll();

            case 'revenue':
                $stmt = $db->prepare(
                    "SELECT DATE(payment_date) AS payment_date, payment_method, amount, status, transaction_reference
                     FROM payments WHERE payment_date BETWEEN ? AND ?
                     ORDER BY payment_date DESC LIMIT {$limit}"
                );
                $stmt->execute([$from, $to]);
                return $stmt->fetchAll();

            case 'inventory':
                return $db->query(
                    "SELECT v.name AS vehicle, vv.name AS variant, vv.sku, w.name AS warehouse,
                            i.quantity_available, i.quantity_reserved, i.min_stock_level
                     FROM inventory i
                     JOIN vehicles v ON v.id = i.vehicle_id
                     JOIN vehicle_variants vv ON vv.id = i.variant_id
                     JOIN warehouses w ON w.id = i.warehouse_id
                     ORDER BY v.name LIMIT {$limit}"
                )->fetchAll();

            case 'leads':
                $stmt = $db->prepare(
                    "SELECT l.customer_name, l.customer_phone, l.status, ls.name AS source, DATE(l.created_at) AS created
                     FROM leads l LEFT JOIN lead_sources ls ON ls.id = l.source_id
                     WHERE DATE(l.created_at) BETWEEN ? AND ?
                     ORDER BY l.created_at DESC LIMIT {$limit}"
                );
                $stmt->execute([$from, $to]);
                return $stmt->fetchAll();

            case 'dealers':
                return $db->query(
                    "SELECT business_name, dealer_code, status, total_orders, total_revenue, performance_score
                     FROM dealers ORDER BY total_revenue DESC LIMIT {$limit}"
                )->fetchAll();

            default:
                return [];
        }
    }
}
