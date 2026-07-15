<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

class DashboardController extends Controller
{
    public function home(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        $this->redirect('/login');
    }

    public function index(): void
    {
        require_auth();
        $role = Auth::role();

        if ($role === 'dealer') {
            $this->dealerDashboard();
            return;
        }
        if ($role === 'service') {
            $this->serviceDashboard();
            return;
        }

        $this->adminDashboard();
    }

    private function adminDashboard(): void
    {
        $db = $this->db();

        $dealers = (int)$db->query("SELECT COUNT(*) FROM dealers WHERE status='approved'")->fetchColumn();
        $dealersPrev = (int)$db->query(
            "SELECT COUNT(*) FROM dealers WHERE status='approved' AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')"
        )->fetchColumn();
        $dealersMonth = (int)$db->query(
            "SELECT COUNT(*) FROM dealers WHERE status='approved' AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())"
        )->fetchColumn();
        $dealersPrevMonth = (int)$db->query(
            "SELECT COUNT(*) FROM dealers WHERE status='approved' AND created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')"
        )->fetchColumn();

        $sold = (int)$db->query(
            "SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi
             JOIN orders o ON o.id = oi.order_id WHERE o.status IN ('delivered','shipped','processing','approved')"
        )->fetchColumn();
        $soldMonth = (int)$db->query(
            "SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.status IN ('delivered','shipped','processing','approved')
               AND YEAR(o.created_at)=YEAR(CURDATE()) AND MONTH(o.created_at)=MONTH(CURDATE())"
        )->fetchColumn();
        $soldPrev = (int)$db->query(
            "SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.status IN ('delivered','shipped','processing','approved')
               AND o.created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
               AND o.created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')"
        )->fetchColumn();

        $revenue = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed'")->fetchColumn();
        $revenueMonth = (float)$db->query(
            "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed' AND YEAR(payment_date)=YEAR(CURDATE()) AND MONTH(payment_date)=MONTH(CURDATE())"
        )->fetchColumn();
        $revenuePrev = (float)$db->query(
            "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed'
             AND payment_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
             AND payment_date < DATE_FORMAT(CURDATE(), '%Y-%m-01')"
        )->fetchColumn();

        $leads = (int)$db->query("SELECT COUNT(*) FROM leads WHERE status IN ('new','contacted','qualified')")->fetchColumn();
        $leadsMonth = (int)$db->query(
            "SELECT COUNT(*) FROM leads WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())"
        )->fetchColumn();
        $leadsPrev = (int)$db->query(
            "SELECT COUNT(*) FROM leads WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')"
        )->fetchColumn();

        $payroll = (float)$db->query(
            "SELECT COALESCE(SUM(net_salary),0) FROM salary_records WHERE month=MONTH(CURDATE()) AND year=YEAR(CURDATE())"
        )->fetchColumn();
        $payrollPrev = (float)$db->query(
            "SELECT COALESCE(SUM(net_salary),0) FROM salary_records
             WHERE month=MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND year=YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))"
        )->fetchColumn();

        $bank = (float)$db->query("SELECT COALESCE(SUM(current_balance),0) FROM bank_accounts WHERE is_active=1")->fetchColumn();
        $expenses = (float)$db->query(
            "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE YEAR(expense_date)=YEAR(CURDATE()) AND MONTH(expense_date)=MONTH(CURDATE())"
        )->fetchColumn();
        $expensesPrev = (float)$db->query(
            "SELECT COALESCE(SUM(amount),0) FROM expenses
             WHERE expense_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
             AND expense_date < DATE_FORMAT(CURDATE(), '%Y-%m-01')"
        )->fetchColumn();

        $monthlySales = $db->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COALESCE(SUM(total_amount),0) AS total
             FROM orders
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
             GROUP BY ym ORDER BY ym"
        )->fetchAll();

        $leadSources = $db->query(
            "SELECT COALESCE(ls.name,'Unknown') AS name, COUNT(l.id) AS cnt
             FROM leads l LEFT JOIN lead_sources ls ON ls.id = l.source_id
             GROUP BY ls.name ORDER BY cnt DESC"
        )->fetchAll();

        $topDealers = $db->query(
            "SELECT business_name, dealer_code, total_orders, total_revenue, performance_score
             FROM dealers WHERE status='approved' ORDER BY total_revenue DESC LIMIT 5"
        )->fetchAll();

        $recentOrders = $db->query(
            "SELECT o.*, d.business_name FROM orders o
             LEFT JOIN dealers d ON d.id = o.dealer_id
             ORDER BY o.created_at DESC LIMIT 8"
        )->fetchAll();

        $recentLeads = $db->query(
            "SELECT l.*, ls.name AS source_name FROM leads l
             LEFT JOIN lead_sources ls ON ls.id = l.source_id
             ORDER BY l.created_at DESC LIMIT 8"
        )->fetchAll();

        $this->view('dashboard/admin', [
            'title' => 'Dashboard',
            'stats' => [
                ['label' => 'Dealers', 'value' => $dealers, 'trend' => mom_trend($dealersMonth, $dealersPrevMonth), 'fmt' => 'int'],
                ['label' => 'Vehicles Sold', 'value' => $sold, 'trend' => mom_trend($soldMonth, $soldPrev), 'fmt' => 'int'],
                ['label' => 'Revenue', 'value' => $revenue, 'trend' => mom_trend($revenueMonth, $revenuePrev), 'fmt' => 'money'],
                ['label' => 'Active Leads', 'value' => $leads, 'trend' => mom_trend($leadsMonth, $leadsPrev), 'fmt' => 'int'],
                ['label' => 'HR Payroll', 'value' => $payroll, 'trend' => mom_trend($payroll, $payrollPrev), 'fmt' => 'money'],
                ['label' => 'Bank Balance', 'value' => $bank, 'trend' => 0, 'fmt' => 'money'],
                ['label' => 'Monthly Expenses', 'value' => $expenses, 'trend' => mom_trend($expenses, $expensesPrev), 'fmt' => 'money'],
            ],
            'monthlySales' => $monthlySales,
            'leadSources' => $leadSources,
            'topDealers' => $topDealers,
            'recentOrders' => $recentOrders,
            'recentLeads' => $recentLeads,
        ]);
    }

    private function dealerDashboard(): void
    {
        $db = $this->db();
        $dealerId = Auth::dealerId();
        if (!$dealerId) {
            flash('error', 'Dealer profile not linked.');
            $this->view('dashboard/dealer', [
                'title' => 'Dashboard',
                'stats' => [],
                'recentOrders' => [],
                'leadSources' => [],
            ]);
            return;
        }

        $stmt = $db->prepare('SELECT COUNT(*) FROM orders WHERE dealer_id = ?');
        $stmt->execute([$dealerId]);
        $orders = (int)$stmt->fetchColumn();

        $rev = $db->prepare('SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE dealer_id = ?');
        $rev->execute([$dealerId]);
        $revenue = (float)$rev->fetchColumn();

        $leads = $db->prepare('SELECT COUNT(*) FROM leads WHERE dealer_id = ?');
        $leads->execute([$dealerId]);
        $leadCount = (int)$leads->fetchColumn();

        $recent = $db->prepare('SELECT * FROM orders WHERE dealer_id = ? ORDER BY created_at DESC LIMIT 8');
        $recent->execute([$dealerId]);

        $sources = $db->prepare(
            "SELECT COALESCE(ls.name,'Unknown') AS name, COUNT(l.id) AS cnt
             FROM leads l LEFT JOIN lead_sources ls ON ls.id = l.source_id
             WHERE l.dealer_id = ? GROUP BY ls.name"
        );
        $sources->execute([$dealerId]);

        $this->view('dashboard/dealer', [
            'title' => 'Dashboard',
            'stats' => [
                ['label' => 'My Orders', 'value' => $orders, 'fmt' => 'int'],
                ['label' => 'Revenue', 'value' => $revenue, 'fmt' => 'money'],
                ['label' => 'Leads', 'value' => $leadCount, 'fmt' => 'int'],
            ],
            'recentOrders' => $recent->fetchAll(),
            'leadSources' => $sources->fetchAll(),
        ]);
    }

    private function serviceDashboard(): void
    {
        $db = $this->db();
        $open = (int)$db->query("SELECT COUNT(*) FROM service_requests WHERE status IN ('pending','in_progress')")->fetchColumn();
        $completed = (int)$db->query(
            "SELECT COUNT(*) FROM service_requests WHERE status='completed' AND YEAR(updated_at)=YEAR(CURDATE()) AND MONTH(updated_at)=MONTH(CURDATE())"
        )->fetchColumn();
        $pendingJobs = (int)$db->query("SELECT COUNT(*) FROM job_cards WHERE status IN ('open','in_progress')")->fetchColumn();

        $this->view('dashboard/service', [
            'title' => 'Service Dashboard',
            'stats' => [
                ['label' => 'Open Requests', 'value' => $open],
                ['label' => 'Completed This Month', 'value' => $completed],
                ['label' => 'Pending Job Cards', 'value' => $pendingJobs],
            ],
        ]);
    }
}
