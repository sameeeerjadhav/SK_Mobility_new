<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;

class FinanceController extends Controller
{
    public function index(): void
    {
        require_role('super_admin');
        $tab = $this->input('tab') ?: 'banks';
        $stats = [
            'bank' => (float)$this->db()->query('SELECT COALESCE(SUM(current_balance),0) FROM bank_accounts WHERE is_active=1')->fetchColumn(),
            'loans' => (float)$this->db()->query("SELECT COALESCE(SUM(outstanding_amount),0) FROM loans WHERE status='active'")->fetchColumn(),
            'active_loans' => (int)$this->db()->query("SELECT COUNT(*) FROM loans WHERE status='active'")->fetchColumn(),
        ];
        $this->view('finance/index', [
            'title' => 'Finance',
            'stats' => $stats,
            'tab' => $tab,
            'accounts' => $this->db()->query('SELECT * FROM bank_accounts ORDER BY account_name')->fetchAll(),
            'loans' => $this->db()->query('SELECT * FROM loans ORDER BY created_at DESC')->fetchAll(),
        ]);
    }

    public function storeAccount(): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $this->db()->prepare(
            'INSERT INTO bank_accounts (account_name, bank_name, account_number, ifsc_code, account_type, current_balance, is_active)
             VALUES (?,?,?,?,?,?,1)'
        )->execute([
            $this->input('account_name'), $this->input('bank_name'), $this->input('account_number'),
            $this->input('ifsc_code'), $this->input('account_type') ?: 'current',
            (float)$this->input('current_balance'),
        ]);
        Audit::log('create', 'finance', 'bank_accounts', (int)$this->db()->lastInsertId());
        flash('success', 'Bank account added.');
        $this->redirect('/finance?tab=banks');
    }

    public function updateAccount(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $this->db()->prepare(
            'UPDATE bank_accounts SET account_name=?, bank_name=?, account_number=?, ifsc_code=?, account_type=?, current_balance=?, is_active=? WHERE id=?'
        )->execute([
            $this->input('account_name'), $this->input('bank_name'), $this->input('account_number'),
            $this->input('ifsc_code'), $this->input('account_type'),
            (float)$this->input('current_balance'), (int)$this->input('is_active'), (int)$id,
        ]);
        Audit::log('update', 'finance', 'bank_accounts', (int)$id);
        flash('success', 'Account updated.');
        $this->redirect('/finance?tab=banks');
    }

    public function deleteAccount(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $this->db()->prepare('DELETE FROM bank_accounts WHERE id = ?')->execute([(int)$id]);
        Audit::log('delete', 'finance', 'bank_accounts', (int)$id);
        flash('success', 'Account deleted.');
        $this->redirect('/finance?tab=banks');
    }

    public function storeLoan(): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $principal = (float)$this->input('principal_amount');
        $this->db()->prepare(
            'INSERT INTO loans (lender_name, loan_type, principal_amount, interest_rate, tenure_months, emi_amount, start_date, end_date, outstanding_amount, status, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $this->input('lender_name'),
            $this->input('loan_type') ?: 'business',
            $principal,
            (float)$this->input('interest_rate'),
            (int)$this->input('tenure_months'),
            (float)$this->input('emi_amount'),
            $this->input('start_date') ?: null,
            $this->input('end_date') ?: null,
            $this->input('outstanding_amount') !== '' ? (float)$this->input('outstanding_amount') : $principal,
            'active',
            $this->input('notes'),
        ]);
        Audit::log('create', 'finance', 'loans', (int)$this->db()->lastInsertId());
        flash('success', 'Loan added.');
        $this->redirect('/finance?tab=loans');
    }

    public function updateLoan(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $this->db()->prepare(
            'UPDATE loans SET lender_name=?, loan_type=?, principal_amount=?, interest_rate=?, tenure_months=?, emi_amount=?, start_date=?, end_date=?, outstanding_amount=?, status=?, notes=? WHERE id=?'
        )->execute([
            $this->input('lender_name'), $this->input('loan_type'),
            (float)$this->input('principal_amount'), (float)$this->input('interest_rate'),
            (int)$this->input('tenure_months'), (float)$this->input('emi_amount'),
            $this->input('start_date') ?: null, $this->input('end_date') ?: null,
            (float)$this->input('outstanding_amount'), $this->input('status'),
            $this->input('notes'), (int)$id,
        ]);
        Audit::log('update', 'finance', 'loans', (int)$id);
        flash('success', 'Loan updated.');
        $this->redirect('/finance?tab=loans');
    }

    public function deleteLoan(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $this->db()->prepare('DELETE FROM loans WHERE id = ?')->execute([(int)$id]);
        Audit::log('delete', 'finance', 'loans', (int)$id);
        flash('success', 'Loan deleted.');
        $this->redirect('/finance?tab=loans');
    }
}
