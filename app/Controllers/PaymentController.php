<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;

class PaymentController extends Controller
{
    public function index(): void
    {
        require_permission('view_payments');

        $db = $this->db();
        $totals = [
            'all' => (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed'")->fetchColumn(),
            'month' => (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed' AND YEAR(payment_date)=YEAR(CURDATE()) AND MONTH(payment_date)=MONTH(CURDATE())")->fetchColumn(),
            'year' => (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed' AND YEAR(payment_date)=YEAR(CURDATE())")->fetchColumn(),
        ];
        $byMethod = $db->query(
            "SELECT payment_method, COALESCE(SUM(amount),0) AS total
             FROM payments WHERE status='completed' GROUP BY payment_method"
        )->fetchAll();

        $where = ['1=1'];
        $params = [];
        if (Auth::role() === 'dealer') {
            $where[] = 'p.dealer_id = ?';
            $params[] = Auth::dealerId();
        }
        $sqlWhere = implode(' AND ', $where);

        $payments = $db->prepare(
            "SELECT p.*, o.order_number FROM payments p
             JOIN orders o ON o.id = p.order_id
             WHERE {$sqlWhere}
             ORDER BY p.created_at DESC LIMIT 100"
        );
        $payments->execute($params);

        $orderSummaries = $db->prepare(
            "SELECT o.id, o.order_number, o.total_amount, o.order_type,
                    COALESCE(SUM(CASE WHEN p.status='completed' THEN p.amount ELSE 0 END),0) AS paid
             FROM orders o
             LEFT JOIN payments p ON p.order_id = o.id
             " . (Auth::role() === 'dealer' ? 'WHERE o.dealer_id = ' . (int)Auth::dealerId() : '') . "
             GROUP BY o.id
             ORDER BY o.created_at DESC LIMIT 50"
        );
        $orderSummaries->execute();

        $orders = [];
        if (can('manage_payments')) {
            $orders = $db->query(
                "SELECT id, order_number, total_amount, dealer_id FROM orders ORDER BY created_at DESC LIMIT 200"
            )->fetchAll();
        }

        $this->view('payments/index', [
            'title' => 'Payments',
            'totals' => $totals,
            'byMethod' => $byMethod,
            'payments' => $payments->fetchAll(),
            'orderSummaries' => $orderSummaries->fetchAll(),
            'orders' => $orders,
            'canManage' => can('manage_payments'),
            'razorpayKey' => env('RAZORPAY_KEY_ID', ''),
        ]);
    }

    public function store(): void
    {
        require_permission('manage_payments');
        $this->validateCsrf();

        $orderId = (int)$this->input('order_id');
        $amount = (float)$this->input('amount');
        $method = $this->input('payment_method') ?: 'cash';
        $date = $this->input('payment_date') ?: date('Y-m-d');
        $ref = $this->input('transaction_reference');
        $notes = $this->input('notes');

        $orderStmt = $this->db()->prepare('SELECT * FROM orders WHERE id = ?');
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch();
        if (!$order || $amount <= 0) {
            flash('error', 'Invalid order or amount.');
            $this->redirect('/payments');
        }

        $this->db()->prepare(
            'INSERT INTO payments (order_id, dealer_id, amount, payment_method, payment_date, transaction_reference, status, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([
            $orderId, $order['dealer_id'], $amount, $method, $date, $ref,
            'completed', $notes, Auth::id(),
        ]);
        $id = (int)$this->db()->lastInsertId();
        Audit::log('create', 'payments', 'payments', $id, null, ['amount' => $amount, 'order_id' => $orderId]);
        flash('success', 'Payment recorded.');
        $this->redirect('/payments');
    }
}
