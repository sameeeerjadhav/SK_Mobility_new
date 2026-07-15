<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Upload;

class ExpenseController extends Controller
{
    public function index(): void
    {
        require_role('super_admin');
        $categoryId = $this->input('category_id');
        $from = $this->input('from');
        $to = $this->input('to');

        $stats = [
            'month' => (float)$this->db()->query(
                "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE YEAR(expense_date)=YEAR(CURDATE()) AND MONTH(expense_date)=MONTH(CURDATE())"
            )->fetchColumn(),
            'year' => (float)$this->db()->query(
                "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE YEAR(expense_date)=YEAR(CURDATE())"
            )->fetchColumn(),
        ];
        $byCat = $this->db()->query(
            "SELECT ec.name, COALESCE(SUM(e.amount),0) AS total
             FROM expenses e JOIN expense_categories ec ON ec.id = e.category_id
             WHERE YEAR(e.expense_date)=YEAR(CURDATE()) AND MONTH(e.expense_date)=MONTH(CURDATE())
             GROUP BY ec.name ORDER BY total DESC"
        )->fetchAll();

        $where = ['1=1'];
        $params = [];
        if ($categoryId !== '') {
            $where[] = 'e.category_id = ?';
            $params[] = (int)$categoryId;
        }
        if ($from !== '') {
            $where[] = 'e.expense_date >= ?';
            $params[] = $from;
        }
        if ($to !== '') {
            $where[] = 'e.expense_date <= ?';
            $params[] = $to;
        }
        $sqlWhere = implode(' AND ', $where);

        $expenses = $this->db()->prepare(
            "SELECT e.*, ec.name AS category_name FROM expenses e
             JOIN expense_categories ec ON ec.id = e.category_id
             WHERE {$sqlWhere} ORDER BY e.expense_date DESC LIMIT 200"
        );
        $expenses->execute($params);

        $this->view('expenses/index', [
            'title' => 'Office Expenses',
            'stats' => $stats,
            'byCat' => $byCat,
            'expenses' => $expenses->fetchAll(),
            'categories' => $this->db()->query('SELECT * FROM expense_categories WHERE is_active=1 ORDER BY name')->fetchAll(),
            'categoryId' => $categoryId,
            'from' => $from,
            'to' => $to,
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
            'INSERT INTO expenses (category_id, amount, description, expense_date, payment_mode, receipt_url, created_by)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([
            (int)$this->input('category_id'),
            (float)$this->input('amount'),
            $this->input('description'),
            $this->input('expense_date') ?: date('Y-m-d'),
            $this->input('payment_mode') ?: 'cash',
            $receipt,
            Auth::id(),
        ]);
        Audit::log('create', 'expenses', 'expenses', (int)$this->db()->lastInsertId());
        flash('success', 'Expense recorded.');
        $this->redirect('/expenses');
    }

    public function update(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $this->db()->prepare(
            'UPDATE expenses SET category_id=?, amount=?, description=?, expense_date=?, payment_mode=? WHERE id=?'
        )->execute([
            (int)$this->input('category_id'),
            (float)$this->input('amount'),
            $this->input('description'),
            $this->input('expense_date'),
            $this->input('payment_mode'),
            (int)$id,
        ]);
        Audit::log('update', 'expenses', 'expenses', (int)$id);
        flash('success', 'Expense updated.');
        $this->redirect('/expenses');
    }

    public function destroy(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $this->db()->prepare('DELETE FROM expenses WHERE id = ?')->execute([(int)$id]);
        Audit::log('delete', 'expenses', 'expenses', (int)$id);
        flash('success', 'Expense deleted.');
        $this->redirect('/expenses');
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
}
