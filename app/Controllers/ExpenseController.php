<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Upload;
use PDOException;
use RuntimeException;

class ExpenseController extends Controller
{
    private const CGST_RATE = 9.0;
    private const SGST_RATE = 9.0;

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
        $sumExpr = 'COALESCE(NULLIF(e.total_amount, 0), e.amount)';

        $stats = [
            'month_assets' => (float)$this->db()->query(
                "SELECT COALESCE(SUM({$sumExpr}),0) FROM expenses e
                 WHERE record_type='asset' AND YEAR(expense_date)=YEAR(CURDATE()) AND MONTH(expense_date)=MONTH(CURDATE())"
            )->fetchColumn(),
            'month_expenditure' => (float)$this->db()->query(
                "SELECT COALESCE(SUM({$sumExpr}),0) FROM expenses e
                 WHERE record_type='expenditure' AND YEAR(expense_date)=YEAR(CURDATE()) AND MONTH(expense_date)=MONTH(CURDATE())"
            )->fetchColumn(),
            'year_assets' => (float)$this->db()->query(
                "SELECT COALESCE(SUM({$sumExpr}),0) FROM expenses e
                 WHERE record_type='asset' AND YEAR(expense_date)=YEAR(CURDATE())"
            )->fetchColumn(),
            'year_expenditure' => (float)$this->db()->query(
                "SELECT COALESCE(SUM({$sumExpr}),0) FROM expenses e
                 WHERE record_type='expenditure' AND YEAR(expense_date)=YEAR(CURDATE())"
            )->fetchColumn(),
        ];
        $stats['month_total'] = $stats['month_assets'] + $stats['month_expenditure'];
        $stats['year_total'] = $stats['year_assets'] + $stats['year_expenditure'];

        $filteredTotalStmt = $this->db()->prepare(
            "SELECT COALESCE(SUM({$sumExpr}),0), COUNT(*)
             FROM expenses e
             JOIN expense_categories ec ON ec.id = e.category_id
             WHERE {$sqlWhere}"
        );
        $filteredTotalStmt->execute($params);
        [$filteredSum, $filteredCount] = $filteredTotalStmt->fetch(\PDO::FETCH_NUM);
        $pager = paginate((int)$filteredCount, max(1, (int)($this->input('page') ?: 1)), 25);

        $expenses = $this->db()->prepare(
            "SELECT e.*, ec.name AS category_name,
                    u.first_name, u.last_name
             FROM expenses e
             JOIN expense_categories ec ON ec.id = e.category_id
             JOIN users u ON u.id = e.created_by
             WHERE {$sqlWhere}
             ORDER BY e.expense_date DESC, e.id DESC
             LIMIT {$pager['per_page']} OFFSET {$pager['offset']}"
        );
        $expenses->execute($params);
        $rows = $expenses->fetchAll();
        $this->attachItems($rows);

        $editId = (int)($this->input('edit') ?? 0);
        $editExpense = $editId > 0 ? $this->findExpense($editId) : null;

        $this->view('expenses/index', [
            'title' => 'Assets & Expenditure',
            'stats' => $stats,
            'expenses' => $rows,
            'categories' => $this->db()->query('SELECT * FROM expense_categories WHERE is_active=1 ORDER BY name')->fetchAll(),
            'allCategories' => $this->db()->query('SELECT * FROM expense_categories ORDER BY name')->fetchAll(),
            'recordTypes' => self::recordTypes(),
            'categoryId' => $categoryId,
            'recordType' => $recordType,
            'paymentMode' => $paymentMode,
            'search' => $search,
            'from' => $from,
            'to' => $to,
            'filteredSum' => (float)$filteredSum,
            'filteredCount' => (int)$filteredCount,
            'editExpense' => $editExpense,
            'pagination' => $pager,
            'filters' => [
                'category_id' => $categoryId,
                'record_type' => $recordType,
                'payment_mode' => $paymentMode,
                'search' => $search,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    public function show(string $id): void
    {
        require_role('super_admin');
        $expense = $this->findExpense((int)$id);
        $total = (float)($expense['total_amount'] ?? 0) > 0
            ? (float)$expense['total_amount']
            : (float)$expense['amount'];

        $this->view('expenses/show', [
            'title' => $expense['name'] ?: 'Expense #' . $expense['id'],
            'expense' => $expense,
            'items' => $expense['items'] ?? [],
            'total' => $total,
        ]);
    }

    public function store(): void
    {
        require_role('super_admin');
        $this->validateCsrf();

        try {
            $payload = $this->validatedPayload();
            $receipt = $this->uploadReceipt();

            $db = $this->db();
            $db->beginTransaction();
            try {
                $db->prepare(
                    'INSERT INTO expenses (
                        category_id, record_type, name, amount, gst_applicable,
                        cgst_amount, sgst_amount, total_amount,
                        description, expense_date, payment_mode, receipt_url, created_by
                     ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $payload['category_id'],
                    $payload['record_type'],
                    $payload['name'],
                    $payload['amount'],
                    $payload['gst_applicable'],
                    $payload['cgst_amount'],
                    $payload['sgst_amount'],
                    $payload['total_amount'],
                    $payload['description'],
                    $payload['expense_date'],
                    $payload['payment_mode'],
                    $receipt,
                    Auth::id(),
                ]);

                $expenseId = (int)$db->lastInsertId();
                $this->saveItems($expenseId, $payload['items']);
                $db->commit();
            } catch (\Throwable $e) {
                $db->rollBack();
                throw $e;
            }

            Audit::log('create', 'expenses', 'expenses', $expenseId);
            flash('success', 'Record saved.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        } catch (PDOException $e) {
            flash('error', 'Could not save expense. Run /install.php?migrate_expenses=1 once, then try again.');
        }

        $this->redirect('/expenses' . $this->redirectFilters());
    }

    public function update(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $expenseId = (int)$id;

        try {
            $payload = $this->validatedPayload();
            $receipt = $this->uploadReceipt();

            $exists = $this->db()->prepare('SELECT id, receipt_url FROM expenses WHERE id = ?');
            $exists->execute([$expenseId]);
            $row = $exists->fetch();
            if (!$row) {
                throw new RuntimeException('Expense record not found.');
            }

            $db = $this->db();
            $db->beginTransaction();
            try {
                if ($receipt) {
                    if (!empty($row['receipt_url'])) {
                        Upload::delete((string)$row['receipt_url']);
                    }
                    $db->prepare(
                        'UPDATE expenses SET
                            category_id=?, record_type=?, name=?, amount=?, gst_applicable=?,
                            cgst_amount=?, sgst_amount=?, total_amount=?,
                            description=?, expense_date=?, payment_mode=?, receipt_url=?
                         WHERE id=?'
                    )->execute([
                        $payload['category_id'],
                        $payload['record_type'],
                        $payload['name'],
                        $payload['amount'],
                        $payload['gst_applicable'],
                        $payload['cgst_amount'],
                        $payload['sgst_amount'],
                        $payload['total_amount'],
                        $payload['description'],
                        $payload['expense_date'],
                        $payload['payment_mode'],
                        $receipt,
                        $expenseId,
                    ]);
                } else {
                    $db->prepare(
                        'UPDATE expenses SET
                            category_id=?, record_type=?, name=?, amount=?, gst_applicable=?,
                            cgst_amount=?, sgst_amount=?, total_amount=?,
                            description=?, expense_date=?, payment_mode=?
                         WHERE id=?'
                    )->execute([
                        $payload['category_id'],
                        $payload['record_type'],
                        $payload['name'],
                        $payload['amount'],
                        $payload['gst_applicable'],
                        $payload['cgst_amount'],
                        $payload['sgst_amount'],
                        $payload['total_amount'],
                        $payload['description'],
                        $payload['expense_date'],
                        $payload['payment_mode'],
                        $expenseId,
                    ]);
                }

                $this->saveItems($expenseId, $payload['items']);
                $db->commit();
            } catch (\Throwable $e) {
                $db->rollBack();
                throw $e;
            }

            Audit::log('update', 'expenses', 'expenses', $expenseId);
            flash('success', 'Record updated.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        } catch (PDOException $e) {
            flash('error', 'Could not update expense. Run /install.php?migrate_expenses=1 once, then try again.');
        }

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
        $name = trim((string)$this->input('name'));
        if ($name === '') {
            flash('error', 'Category name is required.');
            $this->redirect('/expenses');
        }
        $this->db()->prepare('INSERT INTO expense_categories (name, description, is_active) VALUES (?,?,1)')
            ->execute([$name, $this->input('description')]);
        flash('success', 'Category created.');
        $this->redirect('/expenses');
    }

    public function updateCategory(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $catId = (int)$id;
        $name = trim((string)$this->input('name'));
        if ($name === '') {
            flash('error', 'Category name is required.');
            $this->redirect('/expenses');
        }
        $this->db()->prepare('UPDATE expense_categories SET name=?, description=? WHERE id=?')
            ->execute([$name, $this->input('description'), $catId]);
        flash('success', 'Category updated.');
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

    /** @return array<string, mixed> */
    private function validatedPayload(): array
    {
        $items = $this->parseItems();
        if ($items === []) {
            throw new RuntimeException('Add at least one item with a name and base amount.');
        }

        $categoryId = (int)$this->input('category_id');
        if ($categoryId <= 0) {
            throw new RuntimeException('Please select a category. Add one first if the list is empty.');
        }

        $amount = 0.0;
        $names = [];
        foreach ($items as $item) {
            $amount += $item['amount'];
            $names[] = $item['name'];
        }
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new RuntimeException('Total base amount must be greater than zero.');
        }

        $gstApplicable = isset($_POST['gst_applicable']) && $_POST['gst_applicable'] === '1';
        [$cgst, $sgst, $total] = self::computeGst($amount, $gstApplicable);

        $name = implode(', ', $names);
        if (strlen($name) > 150) {
            $name = substr($name, 0, 147) . '...';
        }

        return [
            'category_id' => $categoryId,
            'record_type' => $this->validRecordType($this->input('record_type')),
            'name' => $name,
            'amount' => $amount,
            'gst_applicable' => $gstApplicable ? 1 : 0,
            'cgst_amount' => $cgst,
            'sgst_amount' => $sgst,
            'total_amount' => $total,
            'description' => trim((string)$this->input('description')),
            'expense_date' => $this->input('expense_date') ?: date('Y-m-d'),
            'payment_mode' => $this->validPaymentMode($this->input('payment_mode')),
            'items' => $items,
        ];
    }

    /** @return list<array{name: string, amount: float}> */
    private function parseItems(): array
    {
        $names = $_POST['item_name'] ?? null;
        $amounts = $_POST['item_amount'] ?? null;

        // Legacy single-field fallback
        if (!is_array($names) || !is_array($amounts)) {
            $legacyName = trim((string)$this->input('name'));
            $legacyAmount = (float)$this->input('amount');
            if ($legacyName !== '' && $legacyAmount > 0) {
                return [['name' => $legacyName, 'amount' => round($legacyAmount, 2)]];
            }
            return [];
        }

        $items = [];
        $count = max(count($names), count($amounts));
        for ($i = 0; $i < $count; $i++) {
            $name = trim((string)($names[$i] ?? ''));
            $amount = round((float)($amounts[$i] ?? 0), 2);
            if ($name === '' && $amount <= 0) {
                continue;
            }
            if ($name === '') {
                throw new RuntimeException('Each item needs a name (e.g. Laptop, Printer).');
            }
            if ($amount <= 0) {
                throw new RuntimeException('Each item needs a base amount greater than zero.');
            }
            $items[] = ['name' => $name, 'amount' => $amount];
        }

        return $items;
    }

    /** @param list<array{name: string, amount: float}> $items */
    private function saveItems(int $expenseId, array $items): void
    {
        $this->db()->prepare('DELETE FROM expense_items WHERE expense_id = ?')->execute([$expenseId]);
        $ins = $this->db()->prepare(
            'INSERT INTO expense_items (expense_id, name, amount, sort_order) VALUES (?,?,?,?)'
        );
        foreach ($items as $i => $item) {
            $ins->execute([$expenseId, $item['name'], $item['amount'], $i]);
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function attachItems(array &$rows): void
    {
        if ($rows === []) {
            return;
        }

        try {
            $ids = array_map(static fn($r) => (int)$r['id'], $rows);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->db()->prepare(
                "SELECT id, expense_id, name, amount, sort_order
                 FROM expense_items
                 WHERE expense_id IN ({$placeholders})
                 ORDER BY sort_order ASC, id ASC"
            );
            $stmt->execute($ids);
            $byExpense = [];
            foreach ($stmt->fetchAll() as $item) {
                $byExpense[(int)$item['expense_id']][] = $item;
            }
        } catch (PDOException) {
            $byExpense = [];
        }

        foreach ($rows as &$row) {
            $row['items'] = $byExpense[(int)$row['id']] ?? [[
                'name' => $row['name'] ?? '',
                'amount' => (float)$row['amount'],
            ]];
            $row['item_count'] = count($row['items']);
        }
        unset($row);
    }

    /** @return array{0: float, 1: float, 2: float} */
    public static function computeGst(float $amount, bool $gstApplicable): array
    {
        if (!$gstApplicable || $amount <= 0) {
            return [0.0, 0.0, round($amount, 2)];
        }

        $cgst = round($amount * (self::CGST_RATE / 100), 2);
        $sgst = round($amount * (self::SGST_RATE / 100), 2);

        return [$cgst, $sgst, round($amount + $cgst + $sgst, 2)];
    }

    private function uploadReceipt(): ?string
    {
        if (empty($_FILES['receipt']['name'])) {
            return null;
        }

        $receipt = Upload::store($_FILES['receipt'], 'expenses', ['jpg', 'jpeg', 'png', 'pdf']);
        if (!$receipt) {
            throw new RuntimeException('Receipt upload failed. Use JPG, PNG, or PDF.');
        }

        return $receipt;
    }

    private function validRecordType(?string $type): string
    {
        return in_array($type, self::recordTypes(), true) ? $type : 'expenditure';
    }

    private function validPaymentMode(?string $mode): string
    {
        $allowed = ['cash', 'bank', 'upi', 'card', 'cheque'];
        return in_array($mode, $allowed, true) ? $mode : 'cash';
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
            $where[] = '(e.name LIKE ? OR e.description LIKE ? OR ec.name LIKE ?
                        OR EXISTS (SELECT 1 FROM expense_items ei WHERE ei.expense_id = e.id AND ei.name LIKE ?))';
            $q = '%' . $search . '%';
            array_push($params, $q, $q, $q, $q);
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

    /** @return array<string, mixed> */
    private function findExpense(int $id): array
    {
        $stmt = $this->db()->prepare(
            "SELECT e.*, ec.name AS category_name, u.first_name, u.last_name
             FROM expenses e
             JOIN expense_categories ec ON ec.id = e.category_id
             JOIN users u ON u.id = e.created_by
             WHERE e.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            flash('error', 'Expense record not found.');
            $this->redirect('/expenses');
        }

        $items = $this->db()->prepare(
            'SELECT id, expense_id, name, amount, sort_order
             FROM expense_items WHERE expense_id = ? ORDER BY sort_order ASC, id ASC'
        );
        $items->execute([$id]);
        $row['items'] = $items->fetchAll();
        if ($row['items'] === []) {
            $row['items'] = [[
                'name' => $row['name'] ?? '',
                'amount' => (float)$row['amount'],
            ]];
        }
        $row['item_count'] = count($row['items']);

        return $row;
    }
}
