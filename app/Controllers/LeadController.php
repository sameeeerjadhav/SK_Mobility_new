<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;

class LeadController extends Controller
{
    public function index(): void
    {
        require_permission('view_leads');
        $status = $this->input('status');
        $search = $this->input('search');
        $sourceId = $this->input('source_id');

        $where = ['1=1'];
        $params = [];
        if (Auth::role() === 'dealer') {
            $where[] = 'l.dealer_id = ?';
            $params[] = Auth::dealerId();
        }
        if ($status !== '') {
            $where[] = 'l.status = ?';
            $params[] = $status;
        }
        if ($sourceId !== '') {
            $where[] = 'l.source_id = ?';
            $params[] = (int)$sourceId;
        }
        if ($search !== '') {
            $where[] = '(l.customer_name LIKE ? OR l.customer_phone LIKE ? OR l.customer_email LIKE ?)';
            $q = '%' . $search . '%';
            array_push($params, $q, $q, $q);
        }
        $sqlWhere = implode(' AND ', $where);

        $funnelParams = [];
        $funnelWhere = ['1=1'];
        if (Auth::role() === 'dealer') {
            $funnelWhere[] = 'dealer_id = ?';
            $funnelParams[] = Auth::dealerId();
        }
        $fw = implode(' AND ', $funnelWhere);
        $funnelStmt = $this->db()->prepare(
            "SELECT status, COUNT(*) AS cnt FROM leads WHERE {$fw} GROUP BY status"
        );
        $funnelStmt->execute($funnelParams);
        $funnel = ['new' => 0, 'contacted' => 0, 'qualified' => 0, 'converted' => 0, 'lost' => 0];
        foreach ($funnelStmt->fetchAll() as $row) {
            $funnel[$row['status']] = (int)$row['cnt'];
        }

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM leads l WHERE {$sqlWhere}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $pager = paginate($total, max(1, (int)($this->input('page') ?: 1)), 20);

        $stmt = $this->db()->prepare(
            "SELECT l.*, ls.name AS source_name, v.name AS vehicle_name, d.business_name,
                    u.first_name AS assigned_first, u.last_name AS assigned_last
             FROM leads l
             LEFT JOIN lead_sources ls ON ls.id = l.source_id
             LEFT JOIN vehicles v ON v.id = l.interested_vehicle_id
             LEFT JOIN dealers d ON d.id = l.dealer_id
             LEFT JOIN users u ON u.id = l.assigned_to
             WHERE {$sqlWhere}
             ORDER BY l.created_at DESC
             LIMIT {$pager['per_page']} OFFSET {$pager['offset']}"
        );
        $stmt->execute($params);

        $this->view('leads/index', [
            'title' => 'Leads',
            'leads' => $stmt->fetchAll(),
            'funnel' => $funnel,
            'status' => $status,
            'search' => $search,
            'sourceId' => $sourceId,
            'sources' => $this->db()->query('SELECT * FROM lead_sources WHERE is_active = 1')->fetchAll(),
            'vehicles' => $this->db()->query('SELECT id, name FROM vehicles WHERE is_active = 1 ORDER BY name')->fetchAll(),
            'dealers' => Auth::role() === 'super_admin'
                ? $this->db()->query("SELECT id, business_name FROM dealers WHERE status='approved' ORDER BY business_name")->fetchAll()
                : [],
            'canManage' => can('manage_leads'),
            'pagination' => $pager,
            'filters' => [
                'status' => $status,
                'search' => $search,
                'source_id' => $sourceId,
            ],
        ]);
    }

    public function store(): void
    {
        require_permission('manage_leads');
        $this->validateCsrf();
        $dealerId = Auth::role() === 'dealer' ? Auth::dealerId() : ((int)$this->input('dealer_id') ?: null);

        $this->db()->prepare(
            'INSERT INTO leads (customer_name, customer_phone, customer_email, source_id, interested_vehicle_id, status, notes, assigned_to, dealer_id)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([
            $this->input('customer_name'),
            trim((string)$this->input('customer_phone')) !== '' ? format_phone($this->input('customer_phone')) : null,
            $this->input('customer_email') ?: null,
            $this->input('source_id') !== '' ? (int)$this->input('source_id') : null,
            $this->input('interested_vehicle_id') !== '' ? (int)$this->input('interested_vehicle_id') : null,
            'new',
            $this->input('notes'),
            Auth::id(),
            $dealerId,
        ]);
        $id = (int)$this->db()->lastInsertId();
        Audit::log('create', 'leads', 'leads', $id);
        flash('success', 'Lead created.');
        $this->redirect('/leads');
    }

    public function updateStatus(string $id): void
    {
        require_permission('manage_leads');
        $this->validateCsrf();
        $leadId = (int)$id;
        $status = $this->input('status');
        $notes = $this->input('notes');
        if (!in_array($status, ['new', 'contacted', 'qualified', 'converted', 'lost'], true)) {
            flash('error', 'Invalid status.');
            $this->redirect('/leads');
        }

        $stmt = $this->db()->prepare('SELECT * FROM leads WHERE id = ?');
        $stmt->execute([$leadId]);
        $lead = $stmt->fetch();
        if (!$lead || (Auth::role() === 'dealer' && (int)$lead['dealer_id'] !== Auth::dealerId())) {
            flash('error', 'Lead not found.');
            $this->redirect('/leads');
        }

        $this->db()->prepare('UPDATE leads SET status = ?, notes = CONCAT(COALESCE(notes,""), ?) WHERE id = ?')
            ->execute([$status, $notes ? "\n[" . date('d/m/Y') . "] " . $notes : '', $leadId]);
        Audit::log('update', 'leads', 'leads', $leadId, ['status' => $lead['status']], ['status' => $status]);
        flash('success', 'Lead status updated.');
        $this->redirect('/leads');
    }

    public function addFollowup(string $id): void
    {
        require_permission('manage_leads');
        $this->validateCsrf();
        $leadId = (int)$id;
        $this->db()->prepare(
            'INSERT INTO lead_followups (lead_id, note, follow_up_date, created_by) VALUES (?,?,?,?)'
        )->execute([
            $leadId,
            $this->input('note'),
            $this->input('follow_up_date') ?: null,
            Auth::id(),
        ]);
        Audit::log('create', 'leads', 'lead_followups', (int)$this->db()->lastInsertId());
        flash('success', 'Follow-up added.');
        $this->redirect('/leads');
    }

    public function capture(): void
    {
        $this->validateCsrf();
        $name = $this->input('customer_name');
        $phone = trim((string)$this->input('customer_phone'));
        $phone = $phone !== '' ? format_phone($phone) : '';
        if ($name === '' || $phone === '') {
            flash('error', 'Name and phone are required.');
            $this->redirect('/login');
        }
        $source = $this->db()->query("SELECT id FROM lead_sources WHERE name='Website' LIMIT 1")->fetchColumn();
        $this->db()->prepare(
            'INSERT INTO leads (customer_name, customer_phone, customer_email, source_id, status, notes)
             VALUES (?,?,?,?,\'new\',?)'
        )->execute([$name, $phone, $this->input('customer_email') ?: null, $source ?: null, $this->input('notes')]);
        flash('success', 'Thanks! We will contact you soon.');
        $this->redirect('/login');
    }
}
