<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Upload;

class ExpenseController extends Controller
{
    /** @return list<string> */
    public static function recordTypes(): array
    {
        return ['asset', 'expenditure'];
    }

    public function index(): void
    {
        require_role('super_admin');
        $categoryId = $this->input('category_id');
        $recordType = $this->input('record_type');
        $paymentMode = $this->input('payment_mode');
        $search = trim((string)$this->input('search'));
        $from = $this->input('from');
        $to = $this->input('to');

        [$sqlWhere, $params] = $this->filterClause($categoryId, $recordType, $paymentMode, $search, $from, $to);

        $stats = [
            'month_assets' => (float)$this->db()->query(
                "SELECT COALESCE(SUM(amount),0) FROM expenses
                 WHERE record_type='asset' AND YEAR(expense_date)=YEAR(CURDATE()) AND MONTH(expense_date)=MONTH(CURDATE())"
            )->fetchColumn(),
            'month_expenditure' => (float)$this->db()->query(
                "SELECT COALESCE(SUM(amount),0) FROM expenses
                 WHERE record_type='expenditure' AND YEAR(expense_date)=YEAR(CURDATE()) AND MONTH(expense_date)=MONTH(CURDATE())"
            )->fetchColumn(),
            'year_assets' => (float)$this->db()->query(
                "SELECT COALESCE(SUM(amount),0) FROM expenses
                 WHERE record_type='asset' AND YEAR(expense_date)=YEAR(CURDATE())"
            )->fetchColumn(),
            'year_expenditure' => (float)$this->db()->query(
                "SELECT COALESCE(SUM(amount),0) FROM expenses
                 WHERE record_type='expenditure' AND YEAR(expense_date)=YEAR(CURDATE())"
            )->fetchColumn(),
        ];
        $stats['month_total'] = $stats['month_assets'] + $stats['month_expenditure'];
        $stats['year_total'] = $stats['year_assets'] + $stats['year_expenditure'];

        $filteredTotalStmt = $this->db()->prepare(
            "SELECT COALESCE(SUM(e.amount),0), COUNT(*)
             FROM expenses e
             JOIN expense_categories ec ON ec.id = e.category_id
             WHERE {$sqlWhere}"
        );
        $filteredTotalStmt->execute($params);
        [$filteredSum, $filteredCount] = $filteredTotalStmt->fetch(\PDO::FETCH_NUM);

        $expenses = $this->db()->prepare(
            "SELECT e.*, ec.name AS category_name,
                    u.first_name, u.last_name
             FROM expenses e
             JOIN expense_categories ec ON ec.id = e.category_id
             JOIN users u ON u.id = e.created_by
             WHERE {$sqlWhere}
             ORDER BY e.expense_date DESC, e.id DESC
             LIMIT 500"
        );
        $expenses->execute($params);

        $this->view('expenses/index', [
            'title' => 'Assets & Expenditure',
            'stats' => $stats,
            'expenses' => $expenses->fetchAll(),
            'categories' => $this->db()->query('SELECT * FROM expense_categories WHERE is_active=1 ORDER BY name')->fetchAll(),
            'recordTypes' => self::recordTypes(),
            'categoryId' => $categoryId,
            'recordType' => $recordType,
            'paymentMode' => $paymentMode,
            'search' => $search,
            'from' => $from,
            'to' => $to,
            'filteredSum' => (float)$filteredSum,
            'filteredCount' => (int)$filteredCount,
        ]);
    }

    public function store(): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $receipt = null;
        if (!empty($_FILES['receipt']['name'])) {
            $receipt = Upload::store($_FILES['receipt'], 'expenses', ['jpg', 'jpeg', 'png', 'pdf']);
        }
        $this->db()->prepare(
            'INSERT INTO expenses (category_id, record_type, amount, description, expense_date, payment_mode, receipt_url, created_by)
             VALUES (?,?,?,?,?,?,?,?)'
        )->execute([
            (int)$this->input('category_id'),
            $this->validRecordType($this->input('record_type')),
            (float)$this->input('amount'),
            $this->input('description'),
            $this->input('expense_date') ?: date('Y-m-d'),
            $this->input('payment_mode') ?: 'cash',
            $receipt,
            Auth::id(),
        ]);
        Audit::log('create', 'expenses', 'expenses', (int)$this->db()->lastInsertId());
        flash('success', 'Record saved.');
        $this->redirect('/expenses' . $this->redirectFilters());
    }

    public function update(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $expenseId = (int)$id;

        $receipt = null;
        if (!empty($_FILES['receipt']['name'])) {
            $receipt = Upload::store($_FILES['receipt'], 'expenses', ['jpg', 'jpeg', 'png', 'pdf']);
        }

        if ($receipt) {
            $old = $this->db()->prepare('SELECT receipt_url FROM expenses WHERE id = ?');
            $old->execute([$expenseId]);
            $oldReceipt = $old->fetchColumn();
            if ($oldReceipt) {
                Upload::delete((string)$oldReceipt);
            }
            $this->db()->prepare(
                'UPDATE expenses SET category_id=?, record_type=?, amount=?, description=?, expense_date=?, payment_mode=?, receipt_url=? WHERE id=?'
            )->execute([
                (int)$this->input('category_id'),
                $this->validRecordType($this->input('record_type')),
                (float)$this->input('amount'),
                $this->input('description'),
                $this->input('expense_date'),
                $this->input('payment_mode'),
                $receipt,
                $expenseId,
            ]);
        } else {
            $this->db()->prepare(
                'UPDATE expenses SET category_id=?, record_type=?, amount=?, description=?, expense_date=?, payment_mode=? WHERE id=?'
            )->execute([
                (int)$this->input('category_id'),
                $this->validRecordType($this->input('record_type')),
                (float)$this->input('amount'),
                $this->input('description'),
                $this->input('expense_date'),
                $this->input('payment_mode'),
                $expenseId,
            ]);
        }

        Audit::log('update', 'expenses', 'expenses', $expenseId);
        flash('success', 'Record updated.');
        $this->redirect('/expenses' . $this->redirectFilters());
    }

    public function destroy(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $expenseId = (int)$id;

        $stmt = $this->db()->prepare('SELECT receipt_url FROM expenses WHERE id = ?');
        $stmt->execute([$expenseId]);
        $receipt = $stmt->fetchColumn();
        if ($receipt) {
            Upload::delete((string)$receipt);
        }

        $this->db()->prepare('DELETE FROM expenses WHERE id = ?')->execute([$expenseId]);
        Audit::log('delete', 'expenses', 'expenses', $expenseId);
        flash('success', 'Record deleted.');
        $this->redirect('/expenses' . $this->redirectFilters());
    }

    public function storeCategory(): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $this->db()->prepare('INSERT INTO expense_categories (name, description, is_active) VALUES (?,?,1)')
            ->execute([$this->input('name'), $this->input('description')]);
        flash('success', 'Category created.');
        $this->redirect('/expenses');
    }

    public function deleteCategory(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $this->db()->prepare('UPDATE expense_categories SET is_active=0 WHERE id=?')->execute([(int)$id]);
        flash('success', 'Category deactivated.');
        $this->redirect('/expenses');
    }

    private function validRecordType(?string $type): string
    {
        return in_array($type, self::recordTypes(), true) ? $type : 'expenditure';
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function filterClause(
        ?string $categoryId,
        ?string $recordType,
        ?string $paymentMode,
        string $search,
        ?string $from,
        ?string $to
    ): array {
        $where = ['1=1'];
        $params = [];

        if ($categoryId !== '') {
            $where[] = 'e.category_id = ?';
            $params[] = (int)$categoryId;
        }
        if ($recordType !== '' && in_array($recordType, self::recordTypes(), true)) {
            $where[] = 'e.record_type = ?';
            $params[] = $recordType;
        }
        if ($paymentMode !== '' && in_array($paymentMode, ['cash', 'bank', 'upi', 'card', 'cheque'], true)) {
            $where[] = 'e.payment_mode = ?';
            $params[] = $paymentMode;
        }
        if ($search !== '') {
            $where[] = '(e.description LIKE ? OR ec.name LIKE ?)';
            $q = '%' . $search . '%';
            array_push($params, $q, $q);
        }
        if ($from !== '') {
            $where[] = 'e.expense_date >= ?';
            $params[] = $from;
        }
        if ($to !== '') {
            $where[] = 'e.expense_date <= ?';
            $params[] = $to;
        }

        return [implode(' AND ', $where), $params];
    }

    private function redirectFilters(): string
    {
        $qs = trim((string)$this->input('return_filters'));
        return $qs !== '' ? '?' . $qs : '';
    }
}
