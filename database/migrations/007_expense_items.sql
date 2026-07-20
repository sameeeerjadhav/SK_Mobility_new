-- Expense line items (multiple products/bills on one receipt)
-- Run once on existing databases (or use /install.php?migrate_expenses=1)

CREATE TABLE IF NOT EXISTS expense_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  expense_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE,
  INDEX idx_expense_items_expense (expense_id)
) ENGINE=InnoDB;

-- Backfill one line item per existing expense that has none
INSERT INTO expense_items (expense_id, name, amount, sort_order)
SELECT e.id,
       CASE WHEN e.name IS NOT NULL AND e.name <> '' THEN e.name ELSE CONCAT('Item #', e.id) END,
       e.amount,
       0
FROM expenses e
LEFT JOIN expense_items ei ON ei.expense_id = e.id
WHERE ei.id IS NULL;
