-- Asset vs expenditure classification on expense records
-- Run once on existing databases (or use /install.php?migrate_expenses=1)

ALTER TABLE expenses
  ADD COLUMN IF NOT EXISTS record_type ENUM('asset','expenditure') NOT NULL DEFAULT 'expenditure' AFTER category_id;
