<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;

class ServiceController extends Controller
{
    public function index(): void
    {
        require_permission('view_services');
        $status = $this->input('status');
        $search = $this->input('search');

        $where = ['1=1'];
        $params = [];
        if (Auth::role() === 'dealer') {
            $where[] = 'sr.dealer_id = ?';
            $params[] = Auth::dealerId();
        }
        if ($status !== '') {
            $where[] = 'sr.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(sr.request_number LIKE ? OR sr.customer_name LIKE ? OR sr.customer_phone LIKE ? OR sr.vehicle_vin LIKE ?)';
            $q = '%' . $search . '%';
            array_push($params, $q, $q, $q, $q);
        }
        $sqlWhere = implode(' AND ', $where);

        $stmt = $this->db()->prepare(
            "SELECT sr.*, d.business_name,
                (SELECT COUNT(*) FROM job_cards jc WHERE jc.service_request_id = sr.id) AS job_cards_count
             FROM service_requests sr
             LEFT JOIN dealers d ON d.id = sr.dealer_id
             WHERE {$sqlWhere}
             ORDER BY sr.created_at DESC LIMIT 200"
        );
        $stmt->execute($params);

        $this->view('services/index', [
            'title' => 'Services',
            'requests' => $stmt->fetchAll(),
            'status' => $status,
            'search' => $search,
            'technicians' => $this->db()->query('SELECT * FROM technicians WHERE is_available = 1 ORDER BY name')->fetchAll(),
            'canManage' => can('manage_services'),
        ]);
    }

    public function store(): void
    {
        require_permission('manage_services');
        $this->validateCsrf();
        $number = next_code('SR', 'service_requests', 'request_number');
        $dealerId = Auth::role() === 'dealer' ? Auth::dealerId() : ((int)$this->input('dealer_id') ?: null);

        $this->db()->prepare(
            'INSERT INTO service_requests (request_number, customer_name, customer_phone, vehicle_model, vehicle_vin, issue_description, status, dealer_id)
             VALUES (?,?,?,?,?,?,\'pending\',?)'
        )->execute([
            $number,
            $this->input('customer_name'),
            trim((string)$this->input('customer_phone')) !== '' ? format_phone($this->input('customer_phone')) : null,
            $this->input('vehicle_model'),
            $this->input('vehicle_vin'),
            $this->input('issue_description'),
            $dealerId,
        ]);
        $id = (int)$this->db()->lastInsertId();
        Audit::log('create', 'services', 'service_requests', $id);
        flash('success', "Service request {$number} created.");
        $this->redirect('/services');
    }

    public function createJobCard(string $id): void
    {
        require_permission('manage_services');
        $this->validateCsrf();
        $srId = (int)$id;
        $jcNumber = 'JC-' . str_pad((string)((int)$this->db()->query('SELECT COUNT(*) FROM job_cards')->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);

        $this->db()->prepare(
            'INSERT INTO job_cards (job_card_number, service_request_id, technician_id, work_description, status, started_at)
             VALUES (?,?,?,?,\'open\',NOW())'
        )->execute([
            $jcNumber,
            $srId,
            $this->input('technician_id') !== '' ? (int)$this->input('technician_id') : null,
            $this->input('work_description'),
        ]);

        $this->db()->prepare("UPDATE service_requests SET status = 'in_progress' WHERE id = ? AND status = 'pending'")
            ->execute([$srId]);

        Audit::log('create', 'services', 'job_cards', (int)$this->db()->lastInsertId());
        flash('success', "Job card {$jcNumber} created.");
        $this->redirect('/services/' . $srId);
    }

    public function show(string $id): void
    {
        require_permission('view_services');
        $srId = (int)$id;
        $stmt = $this->db()->prepare('SELECT sr.*, d.business_name FROM service_requests sr LEFT JOIN dealers d ON d.id = sr.dealer_id WHERE sr.id = ?');
        $stmt->execute([$srId]);
        $request = $stmt->fetch();
        if (!$request) {
            flash('error', 'Service request not found.');
            $this->redirect('/services');
        }

        $cards = $this->db()->prepare(
            'SELECT jc.*, t.name AS technician_name FROM job_cards jc
             LEFT JOIN technicians t ON t.id = jc.technician_id
             WHERE jc.service_request_id = ? ORDER BY jc.created_at DESC'
        );
        $cards->execute([$srId]);

        $this->view('services/show', [
            'title' => $request['request_number'],
            'request' => $request,
            'jobCards' => $cards->fetchAll(),
            'technicians' => $this->db()->query('SELECT * FROM technicians ORDER BY name')->fetchAll(),
            'canManage' => can('manage_services'),
        ]);
    }

    public function updateJobCard(string $id): void
    {
        require_permission('manage_services');
        $this->validateCsrf();
        $jcId = (int)$id;
        $status = $this->input('status');
        $labour = (float)$this->input('labour_cost');
        $parts = (float)$this->input('parts_cost');
        $total = $labour + $parts;

        $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;
        $this->db()->prepare(
            'UPDATE job_cards SET technician_id=?, work_description=?, parts_used=?, labour_cost=?, parts_cost=?, total_cost=?, status=?, completed_at=COALESCE(?, completed_at) WHERE id=?'
        )->execute([
            $this->input('technician_id') !== '' ? (int)$this->input('technician_id') : null,
            $this->input('work_description'),
            $this->input('parts_used'),
            $labour, $parts, $total, $status, $completedAt, $jcId,
        ]);

        $sr = $this->db()->prepare('SELECT service_request_id FROM job_cards WHERE id = ?');
        $sr->execute([$jcId]);
        $srId = (int)$sr->fetchColumn();

        if ($status === 'completed') {
            $open = $this->db()->prepare("SELECT COUNT(*) FROM job_cards WHERE service_request_id = ? AND status != 'completed'");
            $open->execute([$srId]);
            if ((int)$open->fetchColumn() === 0) {
                $this->db()->prepare("UPDATE service_requests SET status = 'completed' WHERE id = ?")->execute([$srId]);
            }
        }

        Audit::log('update', 'services', 'job_cards', $jcId);
        flash('success', 'Job card updated.');
        $this->redirect('/services/' . $srId);
    }

    public function technicians(): void
    {
        require_permission('manage_services');
        $this->validateCsrf();
        $this->db()->prepare(
            'INSERT INTO technicians (name, phone, email, specialization, is_available) VALUES (?,?,?,?,1)'
        )->execute([
            $this->input('name'),
            trim((string)$this->input('phone')) !== '' ? format_phone($this->input('phone')) : null,
            $this->input('email'),
            $this->input('specialization'),
        ]);
        flash('success', 'Technician added.');
        $this->redirect('/services');
    }
}
