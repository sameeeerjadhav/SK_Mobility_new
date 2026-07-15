<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;

class SparePartController extends Controller
{
    public function index(): void
    {
        require_permission('view_spare_parts');
        $search = $this->input('search');
        $categoryId = $this->input('category_id');
        $tab = $this->input('tab') ?: 'list';

        $where = ['sp.is_active = 1'];
        $params = [];
        if ($search !== '') {
            $where[] = '(sp.name LIKE ? OR sp.part_number LIKE ?)';
            $q = '%' . $search . '%';
            $params[] = $q;
            $params[] = $q;
        }
        if ($categoryId !== '') {
            $where[] = 'sp.category_id = ?';
            $params[] = (int)$categoryId;
        }
        $sqlWhere = implode(' AND ', $where);

        $parts = $this->db()->prepare(
            "SELECT sp.*, sc.name AS category_name FROM spare_parts sp
             JOIN spare_categories sc ON sc.id = sp.category_id
             WHERE {$sqlWhere} ORDER BY sp.name"
        );
        $parts->execute($params);

        $jobCards = $this->db()->query(
            "SELECT jc.id, jc.job_card_number, sr.request_number
             FROM job_cards jc JOIN service_requests sr ON sr.id = jc.service_request_id
             WHERE jc.status != 'completed' ORDER BY jc.id DESC LIMIT 100"
        )->fetchAll();

        $this->view('spare-parts/index', [
            'title' => 'Spare Parts',
            'parts' => $parts->fetchAll(),
            'categories' => $this->db()->query('SELECT * FROM spare_categories WHERE is_active = 1')->fetchAll(),
            'search' => $search,
            'categoryId' => $categoryId,
            'tab' => $tab,
            'jobCards' => $jobCards,
            'canManage' => can('manage_spare_parts'),
        ]);
    }

    public function store(): void
    {
        require_permission('manage_spare_parts');
        $this->validateCsrf();
        $this->db()->prepare(
            'INSERT INTO spare_parts (category_id, name, part_number, description, unit_price, quantity_in_stock, min_stock_level, is_active)
             VALUES (?,?,?,?,?,?,?,1)'
        )->execute([
            (int)$this->input('category_id'),
            $this->input('name'),
            $this->input('part_number'),
            $this->input('description'),
            (float)$this->input('unit_price'),
            (int)$this->input('quantity_in_stock'),
            (int)($this->input('min_stock_level') ?: 5),
        ]);
        Audit::log('create', 'spare_parts', 'spare_parts', (int)$this->db()->lastInsertId());
        flash('success', 'Spare part created.');
        $this->redirect('/spare-parts');
    }

    public function update(string $id): void
    {
        require_permission('manage_spare_parts');
        $this->validateCsrf();
        $partId = (int)$id;
        $this->db()->prepare(
            'UPDATE spare_parts SET category_id=?, name=?, part_number=?, description=?, unit_price=?, quantity_in_stock=?, min_stock_level=? WHERE id=?'
        )->execute([
            (int)$this->input('category_id'),
            $this->input('name'),
            $this->input('part_number'),
            $this->input('description'),
            (float)$this->input('unit_price'),
            (int)$this->input('quantity_in_stock'),
            (int)$this->input('min_stock_level'),
            $partId,
        ]);
        Audit::log('update', 'spare_parts', 'spare_parts', $partId);
        flash('success', 'Spare part updated.');
        $this->redirect('/spare-parts');
    }

    public function destroy(string $id): void
    {
        require_permission('manage_spare_parts');
        $this->validateCsrf();
        $this->db()->prepare('UPDATE spare_parts SET is_active = 0 WHERE id = ?')->execute([(int)$id]);
        Audit::log('delete', 'spare_parts', 'spare_parts', (int)$id);
        flash('success', 'Spare part removed.');
        $this->redirect('/spare-parts');
    }

    public function usage(): void
    {
        require_permission('manage_spare_parts');
        $this->validateCsrf();
        $partId = (int)$this->input('spare_part_id');
        $qty = max(1, (int)$this->input('quantity_used'));
        $jobCardId = $this->input('job_card_id') !== '' ? (int)$this->input('job_card_id') : null;

        $part = $this->db()->prepare('SELECT * FROM spare_parts WHERE id = ?');
        $part->execute([$partId]);
        $p = $part->fetch();
        if (!$p || (int)$p['quantity_in_stock'] < $qty) {
            flash('error', 'Insufficient spare part stock.');
            $this->redirect('/spare-parts');
        }

        $unit = (float)$p['unit_price'];
        $this->db()->prepare(
            'INSERT INTO spare_parts_usage (spare_part_id, job_card_id, quantity_used, unit_price, total_price, notes, created_by)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([$partId, $jobCardId, $qty, $unit, $unit * $qty, $this->input('notes'), Auth::id()]);

        $this->db()->prepare('UPDATE spare_parts SET quantity_in_stock = quantity_in_stock - ? WHERE id = ?')
            ->execute([$qty, $partId]);

        Audit::log('create', 'spare_parts', 'spare_parts_usage', (int)$this->db()->lastInsertId());
        flash('success', 'Usage recorded.');
        $this->redirect('/spare-parts?tab=stock');
    }
}
