<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Services\BankTransactionService;
use RuntimeException;

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

    public function showAccount(string $id): void
    {
        require_role('super_admin');
        $accountId = (int)$id;
        $stmt = $this->db()->prepare('SELECT * FROM bank_accounts WHERE id = ?');
        $stmt->execute([$accountId]);
        $account = $stmt->fetch();
        if (!$account) {
            flash('error', 'Bank account not found.');
            $this->redirect('/finance?tab=banks');
        }

        $service = new BankTransactionService($this->db());
        $this->view('finance/account', [
            'title' => $account['account_name'],
            'account' => $account,
            'transactions' => $service->listForAccount($accountId),
        ]);
    }

    public function storeAccount(): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $opening = round((float)$this->input('current_balance'), 2);
        $db = $this->db();
        $db->beginTransaction();
        try {
            $db->prepare(
                'INSERT INTO bank_accounts (account_name, bank_name, account_number, ifsc_code, account_type, current_balance, is_active)
                 VALUES (?,?,?,?,?,?,1)'
            )->execute([
                $this->input('account_name'),
                $this->input('bank_name'),
                $this->input('account_number'),
                $this->input('ifsc_code') ?: null,
                $this->input('account_type') ?: 'current',
                0,
            ]);
            $id = (int)$db->lastInsertId();
            if ($opening > 0) {
                (new BankTransactionService($db))->credit(
                    $id,
                    $opening,
                    'opening_balance',
                    null,
                    'Opening balance',
                    (int)Auth::id()
                );
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            flash('error', $e->getMessage());
            $this->redirect('/finance?tab=banks');
        }
        Audit::log('create', 'finance', 'bank_accounts', $id);
        flash('success', 'Bank account added.');
        $this->redirect('/finance/bank-accounts/' . $id);
    }

    public function updateAccount(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $accountId = (int)$id;
        $this->db()->prepare(
            'UPDATE bank_accounts SET account_name=?, bank_name=?, account_number=?, ifsc_code=?, account_type=?, is_active=? WHERE id=?'
        )->execute([
            $this->input('account_name'),
            $this->input('bank_name'),
            $this->input('account_number'),
            $this->input('ifsc_code') ?: null,
            $this->input('account_type'),
            (int)$this->input('is_active'),
            $accountId,
        ]);
        Audit::log('update', 'finance', 'bank_accounts', $accountId);
        flash('success', 'Account updated.');
        $this->redirect('/finance/bank-accounts/' . $accountId);
    }

    public function storeTransaction(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $accountId = (int)$id;
        $type = strtolower(trim((string)$this->input('transaction_type')));
        $amount = round((float)$this->input('amount'), 2);
        $description = trim((string)$this->input('description'));
        $date = trim((string)$this->input('transaction_date')) ?: date('Y-m-d');

        try {
            $service = new BankTransactionService($this->db());
            if ($type === 'credit') {
                $service->credit($accountId, $amount, 'manual', null, $description, (int)Auth::id(), $date);
            } elseif ($type === 'debit') {
                $service->debit($accountId, $amount, 'manual', null, $description, (int)Auth::id(), $date);
            } else {
                throw new RuntimeException('Select credit or debit.');
            }
            Audit::log('create', 'finance', 'bank_transactions', $accountId, null, ['type' => $type, 'amount' => $amount]);
            flash('success', ucfirst($type) . ' of ' . number_format($amount, 2) . ' recorded.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        $this->redirect('/finance/bank-accounts/' . $accountId);
    }

    public function deleteAccount(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $accountId = (int)$id;
        $cnt = $this->db()->prepare('SELECT COUNT(*) FROM bank_transactions WHERE bank_account_id = ?');
        $cnt->execute([$accountId]);
        if ((int)$cnt->fetchColumn() > 0) {
            flash('error', 'Cannot delete — this account has ledger transactions. Mark it inactive instead.');
            $this->redirect('/finance/bank-accounts/' . $accountId);
        }
        $this->db()->prepare('DELETE FROM bank_accounts WHERE id = ?')->execute([$accountId]);
        Audit::log('delete', 'finance', 'bank_accounts', $accountId);
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
