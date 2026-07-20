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
            'total_partners' => (int)$this->db()->query('SELECT COUNT(*) FROM partners')->fetchColumn(),
            'paid' => (float)$this->db()->query("SELECT COALESCE(SUM(amount),0) FROM partner_transactions WHERE transaction_type='payment'")->fetchColumn(),
            'received' => (float)$this->db()->query("SELECT COALESCE(SUM(amount),0) FROM partner_transactions WHERE transaction_type='receipt'")->fetchColumn(),
            'transactions' => (int)$this->db()->query('SELECT COUNT(*) FROM partner_transactions')->fetchColumn(),
        ];
        $stats['net'] = $stats['received'] - $stats['paid'];
        $partners = $this->db()->query('SELECT * FROM partners ORDER BY name')->fetchAll();

        $txTotal = (int)$this->db()->query('SELECT COUNT(*) FROM partner_transactions')->fetchColumn();
        $txPager = paginate($txTotal, max(1, (int)($this->input('page') ?: 1)), 25);
        $transactions = $this->db()->query(
            "SELECT pt.*, p.name AS partner_name, u.first_name, u.last_name
             FROM partner_transactions pt
             JOIN partners p ON p.id = pt.partner_id
             JOIN users u ON u.id = pt.created_by
             ORDER BY pt.date DESC, pt.id DESC
             LIMIT {$txPager['per_page']} OFFSET {$txPager['offset']}"
        )->fetchAll();

        $this->view('partners/index', [
            'title' => 'Partners',
            'stats' => $stats,
            'partners' => $partners,
            'transactions' => $transactions,
            'pagination' => $txPager,
            'filters' => [],
        ]);
    }

    public function store(): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $this->db()->prepare(
            'INSERT INTO partners (name, phone, email, address, aadhar_number, pan_number, is_active) VALUES (?,?,?,?,?,?,1)'
        )->execute([
            trim((string)$this->input('name')),
            trim((string)$this->input('phone')) !== '' ? format_phone($this->input('phone')) : '',
            trim((string)$this->input('email')),
            trim((string)$this->input('address')),
            trim((string)$this->input('aadhar_number')) !== '' ? format_aadhar($this->input('aadhar_number')) : null,
            trim((string)$this->input('pan_number')),
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
            'UPDATE partners SET name=?, phone=?, email=?, address=?, aadhar_number=?, pan_number=?, is_active=? WHERE id=?'
        )->execute([
            trim((string)$this->input('name')),
            trim((string)$this->input('phone')) !== '' ? format_phone($this->input('phone')) : '',
            trim((string)$this->input('email')),
            trim((string)$this->input('address')),
            trim((string)$this->input('aadhar_number')) !== '' ? format_aadhar($this->input('aadhar_number')) : null,
            trim((string)$this->input('pan_number')),
            (int)$this->input('is_active'),
            (int)$id,
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

        try {
            $payload = $this->validatedTransactionPayload();
            $this->db()->prepare(
                'INSERT INTO partner_transactions (partner_id, transaction_type, amount, date, description, reference_number, created_by)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([
                $payload['partner_id'],
                $payload['transaction_type'],
                $payload['amount'],
                $payload['date'],
                $payload['description'],
                $payload['reference_number'],
                Auth::id(),
            ]);
            Audit::log('create', 'partners', 'partner_transactions', (int)$this->db()->lastInsertId());
            flash('success', 'Transaction recorded.');
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
        }

        $this->redirect('/partners');
    }

    public function updateTransaction(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $txId = (int)$id;

        try {
            $payload = $this->validatedTransactionPayload();
            $exists = $this->db()->prepare('SELECT id FROM partner_transactions WHERE id = ?');
            $exists->execute([$txId]);
            if (!$exists->fetch()) {
                throw new \RuntimeException('Transaction not found.');
            }

            $this->db()->prepare(
                'UPDATE partner_transactions SET
                    partner_id=?, transaction_type=?, amount=?, date=?, description=?, reference_number=?
                 WHERE id=?'
            )->execute([
                $payload['partner_id'],
                $payload['transaction_type'],
                $payload['amount'],
                $payload['date'],
                $payload['description'],
                $payload['reference_number'],
                $txId,
            ]);
            Audit::log('update', 'partners', 'partner_transactions', $txId);
            flash('success', 'Transaction updated.');
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
        }

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

    /** @return array<string, mixed> */
    private function validatedTransactionPayload(): array
    {
        $partnerId = (int)$this->input('partner_id');
        if ($partnerId <= 0) {
            throw new \RuntimeException('Please select a partner.');
        }

        $partner = $this->db()->prepare('SELECT id FROM partners WHERE id = ?');
        $partner->execute([$partnerId]);
        if (!$partner->fetch()) {
            throw new \RuntimeException('Partner not found.');
        }

        $amount = (float)$this->input('amount');
        if ($amount <= 0) {
            throw new \RuntimeException('Amount must be greater than zero.');
        }

        $type = (string)$this->input('transaction_type');
        if (!in_array($type, ['payment', 'receipt', 'adjustment'], true)) {
            throw new \RuntimeException('Invalid transaction type.');
        }

        return [
            'partner_id' => $partnerId,
            'transaction_type' => $type,
            'amount' => round($amount, 2),
            'date' => $this->input('date') ?: date('Y-m-d'),
            'description' => trim((string)$this->input('description')),
            'reference_number' => trim((string)$this->input('reference_number')),
        ];
    }
}
