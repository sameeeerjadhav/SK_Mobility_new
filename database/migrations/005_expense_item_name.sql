-- Item name on expense / asset records
-- Run once on existing databases (or use /install.php?migrate_expenses=1)

ALTER TABLE expenses
  ADD COLUMN IF NOT EXISTS name VARCHAR(150) NOT NULL DEFAULT '' AFTER record_type;
