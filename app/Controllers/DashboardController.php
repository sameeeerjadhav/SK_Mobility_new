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
        $cacheKey = 'dashboard_admin_v2';
        $cacheAt = (int)($_SESSION[$cacheKey . '_at'] ?? 0);
        if ($cacheAt > 0 && (time() - $cacheAt) < 30 && isset($_SESSION[$cacheKey])) {
            $payload = $_SESSION[$cacheKey];
            $payload['loadCharts'] = true;
            $this->view('dashboard/admin', $payload);
            return;
        }

        $db = $this->db();
        $monthStart = date('Y-m-01');
        $nextMonthStart = date('Y-m-01', strtotime('+1 month'));
        $prevMonthStart = date('Y-m-01', strtotime('-1 month'));

        $dealerRow = $db->query(
            "SELECT
                SUM(status = 'approved') AS dealers,
                SUM(status = 'approved' AND created_at < '{$monthStart}') AS dealers_prev,
                SUM(status = 'approved' AND created_at >= '{$monthStart}' AND created_at < '{$nextMonthStart}') AS dealers_month,
                SUM(status = 'approved' AND created_at >= '{$prevMonthStart}' AND created_at < '{$monthStart}') AS dealers_prev_month
             FROM dealers"
        )->fetch();

        $soldStatuses = "'delivered','shipped','processing','approved'";
        $soldRow = $db->query(
            "SELECT
                COALESCE(SUM(oi.quantity), 0) AS sold,
                COALESCE(SUM(CASE WHEN o.created_at >= '{$monthStart}' AND o.created_at < '{$nextMonthStart}' THEN oi.quantity ELSE 0 END), 0) AS sold_month,
                COALESCE(SUM(CASE WHEN o.created_at >= '{$prevMonthStart}' AND o.created_at < '{$monthStart}' THEN oi.quantity ELSE 0 END), 0) AS sold_prev
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.status IN ({$soldStatuses})"
        )->fetch();

        $payRow = $db->query(
            "SELECT
                COALESCE(SUM(amount), 0) AS revenue,
                COALESCE(SUM(CASE WHEN payment_date >= '{$monthStart}' AND payment_date < '{$nextMonthStart}' THEN amount ELSE 0 END), 0) AS revenue_month,
                COALESCE(SUM(CASE WHEN payment_date >= '{$prevMonthStart}' AND payment_date < '{$monthStart}' THEN amount ELSE 0 END), 0) AS revenue_prev
             FROM payments
             WHERE status = 'completed'"
        )->fetch();

        $leadRow = $db->query(
            "SELECT
                SUM(status IN ('new','contacted','qualified')) AS leads,
                SUM(created_at >= '{$monthStart}' AND created_at < '{$nextMonthStart}') AS leads_month,
                SUM(created_at >= '{$prevMonthStart}' AND created_at < '{$monthStart}') AS leads_prev
             FROM leads"
        )->fetch();

        $thisMonth = (int)date('n');
        $thisYear = (int)date('Y');
        $prevMonth = (int)date('n', strtotime('-1 month'));
        $prevYear = (int)date('Y', strtotime('-1 month'));
        $payrollStmt = $db->prepare(
            'SELECT
                COALESCE(SUM(CASE WHEN month = ? AND year = ? THEN net_salary ELSE 0 END), 0) AS payroll,
                COALESCE(SUM(CASE WHEN month = ? AND year = ? THEN net_salary ELSE 0 END), 0) AS payroll_prev
             FROM salary_records
             WHERE (month = ? AND year = ?) OR (month = ? AND year = ?)'
        );
        $payrollStmt->execute([
            $thisMonth, $thisYear, $prevMonth, $prevYear,
            $thisMonth, $thisYear, $prevMonth, $prevYear,
        ]);
        $payrollRow = $payrollStmt->fetch();

        $bank = (float)$db->query('SELECT COALESCE(SUM(current_balance),0) FROM bank_accounts WHERE is_active=1')->fetchColumn();

        $expenseRow = $db->query(
            "SELECT
                COALESCE(SUM(COALESCE(NULLIF(total_amount, 0), amount)), 0) AS expenses_all,
                COALESCE(SUM(CASE WHEN expense_date >= '{$monthStart}' AND expense_date < '{$nextMonthStart}' THEN COALESCE(NULLIF(total_amount, 0), amount) ELSE 0 END), 0) AS expenses,
                COALESCE(SUM(CASE WHEN expense_date >= '{$prevMonthStart}' AND expense_date < '{$monthStart}' THEN COALESCE(NULLIF(total_amount, 0), amount) ELSE 0 END), 0) AS expenses_prev
             FROM expenses"
        )->fetch();

        $partnerRow = $db->query(
            "SELECT
                COALESCE(SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END), 0) AS partner_paid,
                COALESCE(SUM(CASE WHEN transaction_type = 'receipt' THEN amount ELSE 0 END), 0) AS partner_received,
                COALESCE(SUM(CASE WHEN transaction_type = 'payment' AND date >= '{$monthStart}' AND date < '{$nextMonthStart}' THEN amount ELSE 0 END), 0) AS partner_paid_month,
                COALESCE(SUM(CASE WHEN transaction_type = 'receipt' AND date >= '{$monthStart}' AND date < '{$nextMonthStart}' THEN amount ELSE 0 END), 0) AS partner_received_month,
                COALESCE(SUM(CASE WHEN transaction_type = 'payment' AND date >= '{$prevMonthStart}' AND date < '{$monthStart}' THEN amount ELSE 0 END), 0) AS partner_paid_prev,
                COALESCE(SUM(CASE WHEN transaction_type = 'receipt' AND date >= '{$prevMonthStart}' AND date < '{$monthStart}' THEN amount ELSE 0 END), 0) AS partner_received_prev
             FROM partner_transactions"
        )->fetch();

        $dealers = (int)($dealerRow['dealers'] ?? 0);
        $dealersMonth = (int)($dealerRow['dealers_month'] ?? 0);
        $dealersPrevMonth = (int)($dealerRow['dealers_prev_month'] ?? 0);
        $sold = (int)($soldRow['sold'] ?? 0);
        $soldMonth = (int)($soldRow['sold_month'] ?? 0);
        $soldPrev = (int)($soldRow['sold_prev'] ?? 0);
        $revenue = (float)($payRow['revenue'] ?? 0);
        $revenueMonth = (float)($payRow['revenue_month'] ?? 0);
        $revenuePrev = (float)($payRow['revenue_prev'] ?? 0);
        $leads = (int)($leadRow['leads'] ?? 0);
        $leadsMonth = (int)($leadRow['leads_month'] ?? 0);
        $leadsPrev = (int)($leadRow['leads_prev'] ?? 0);
        $payroll = (float)($payrollRow['payroll'] ?? 0);
        $payrollPrev = (float)($payrollRow['payroll_prev'] ?? 0);
        $expenses = (float)($expenseRow['expenses'] ?? 0);
        $expensesPrev = (float)($expenseRow['expenses_prev'] ?? 0);
        $expensesAll = (float)($expenseRow['expenses_all'] ?? 0);
        $partnerPaid = (float)($partnerRow['partner_paid'] ?? 0);
        $partnerReceived = (float)($partnerRow['partner_received'] ?? 0);
        $partnerPaidMonth = (float)($partnerRow['partner_paid_month'] ?? 0);
        $partnerReceivedMonth = (float)($partnerRow['partner_received_month'] ?? 0);
        $partnerPaidPrev = (float)($partnerRow['partner_paid_prev'] ?? 0);
        $partnerReceivedPrev = (float)($partnerRow['partner_received_prev'] ?? 0);

        $partnerNet = $partnerReceived - $partnerPaid;
        $partnerNetMonth = $partnerReceivedMonth - $partnerPaidMonth;
        $partnerNetPrev = $partnerReceivedPrev - $partnerPaidPrev;
        $balance = $partnerNet - $expensesAll;
        $balanceMonth = $partnerNetMonth - $expenses;
        $balancePrev = $partnerNetPrev - $expensesPrev;

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
            "SELECT o.id, o.order_number, o.order_type, o.status, o.total_amount, o.created_at, o.customer_name, d.business_name
             FROM orders o
             LEFT JOIN dealers d ON d.id = o.dealer_id
             ORDER BY o.created_at DESC LIMIT 8"
        )->fetchAll();

        $recentLeads = $db->query(
            "SELECT l.id, l.customer_name, l.customer_phone, l.status, l.created_at, ls.name AS source_name
             FROM leads l
             LEFT JOIN lead_sources ls ON ls.id = l.source_id
             ORDER BY l.created_at DESC LIMIT 8"
        )->fetchAll();

        $payload = [
            'title' => 'Dashboard',
            'stats' => [
                ['label' => 'Dealers', 'value' => $dealers, 'trend' => mom_trend($dealersMonth, $dealersPrevMonth), 'fmt' => 'int'],
                ['label' => 'Vehicles Sold', 'value' => $sold, 'trend' => mom_trend($soldMonth, $soldPrev), 'fmt' => 'int'],
                ['label' => 'Revenue', 'value' => $revenue, 'trend' => mom_trend($revenueMonth, $revenuePrev), 'fmt' => 'money'],
                ['label' => 'Active Leads', 'value' => $leads, 'trend' => mom_trend($leadsMonth, $leadsPrev), 'fmt' => 'int'],
                ['label' => 'HR Payroll', 'value' => $payroll, 'trend' => mom_trend($payroll, $payrollPrev), 'fmt' => 'money'],
                ['label' => 'Bank Balance', 'value' => $bank, 'trend' => 0, 'fmt' => 'money'],
                ['label' => 'Partner Received', 'value' => $partnerReceived, 'trend' => mom_trend($partnerReceivedMonth, $partnerReceivedPrev), 'fmt' => 'money'],
                ['label' => 'Partner Paid', 'value' => $partnerPaid, 'trend' => mom_trend($partnerPaidMonth, $partnerPaidPrev), 'fmt' => 'money'],
                ['label' => 'Partner Net', 'value' => $partnerNet, 'trend' => mom_trend($partnerNetMonth, $partnerNetPrev), 'fmt' => 'money'],
                ['label' => 'Monthly Expenses', 'value' => $expenses, 'trend' => mom_trend($expenses, $expensesPrev), 'fmt' => 'money'],
                ['label' => 'Available Balance', 'value' => $balance, 'trend' => mom_trend($balanceMonth, $balancePrev), 'fmt' => 'money', 'hint' => 'Partner net − all expenses'],
            ],
            'monthlySales' => $monthlySales,
            'leadSources' => $leadSources,
            'topDealers' => $topDealers,
            'recentOrders' => $recentOrders,
            'recentLeads' => $recentLeads,
        ];

        $_SESSION[$cacheKey] = $payload;
        $_SESSION[$cacheKey . '_at'] = time();

        $payload['loadCharts'] = true;
        $this->view('dashboard/admin', $payload);
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
                ['label' => 'My Sell Orders', 'value' => $orders, 'fmt' => 'int'],
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
