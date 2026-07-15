<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;

class PartnerController extends Controller
{
    public function index(): void
    {
        require_role('super_admin');
        $stats = [
            'partners' => (int)$this->db()->query('SELECT COUNT(*) FROM partners WHERE is_active=1')->fetchColumn(),
            'paid' => (float)$this->db()->query("SELECT COALESCE(SUM(amount),0) FROM partner_transactions WHERE transaction_type='payment'")->fetchColumn(),
            'received' => (float)$this->db()->query("SELECT COALESCE(SUM(amount),0) FROM partner_transactions WHERE transaction_type='receipt'")->fetchColumn(),
        ];
        $partners = $this->db()->query('SELECT * FROM partners ORDER BY name')->fetchAll();
        $transactions = $this->db()->query(
            'SELECT pt.*, p.name AS partner_name FROM partner_transactions pt
             JOIN partners p ON p.id = pt.partner_id ORDER BY pt.date DESC, pt.id DESC LIMIT 100'
        )->fetchAll();

        $this->view('partners/index', [
            'title' => 'Partners',
            'stats' => $stats,
            'partners' => $partners,
            'transactions' => $transactions,
        ]);
    }

    public function store(): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $this->db()->prepare(
            'INSERT INTO partners (name, type, contact_person, phone, email, address, gst_number, is_active) VALUES (?,?,?,?,?,?,?,1)'
        )->execute([
            $this->input('name'), $this->input('type') ?: 'vendor',
            $this->input('contact_person'), $this->input('phone'), $this->input('email'),
            $this->input('address'), $this->input('gst_number'),
        ]);
        Audit::log('create', 'partners', 'partners', (int)$this->db()->lastInsertId());
        flash('success', 'Partner created.');
        $this->redirect('/partners');
    }

    public function update(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $this->db()->prepare(
            'UPDATE partners SET name=?, type=?, contact_person=?, phone=?, email=?, address=?, gst_number=?, is_active=? WHERE id=?'
        )->execute([
            $this->input('name'), $this->input('type'),
            $this->input('contact_person'), $this->input('phone'), $this->input('email'),
            $this->input('address'), $this->input('gst_number'),
            (int)$this->input('is_active'), (int)$id,
        ]);
        Audit::log('update', 'partners', 'partners', (int)$id);
        flash('success', 'Partner updated.');
        $this->redirect('/partners');
    }

    public function destroy(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $this->db()->prepare('DELETE FROM partners WHERE id = ?')->execute([(int)$id]);
        Audit::log('delete', 'partners', 'partners', (int)$id);
        flash('success', 'Partner deleted.');
        $this->redirect('/partners');
    }

    public function storeTransaction(): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $this->db()->prepare(
            'INSERT INTO partner_transactions (partner_id, transaction_type, amount, date, description, reference_number, created_by)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([
            (int)$this->input('partner_id'),
            $this->input('transaction_type'),
            (float)$this->input('amount'),
            $this->input('date') ?: date('Y-m-d'),
            $this->input('description'),
            $this->input('reference_number'),
            Auth::id(),
        ]);
        Audit::log('create', 'partners', 'partner_transactions', (int)$this->db()->lastInsertId());
        flash('success', 'Transaction recorded.');
        $this->redirect('/partners');
    }

    public function deleteTransaction(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $this->db()->prepare('DELETE FROM partner_transactions WHERE id = ?')->execute([(int)$id]);
        Audit::log('delete', 'partners', 'partner_transactions', (int)$id);
        flash('success', 'Transaction deleted.');
        $this->redirect('/partners');
    }
}
