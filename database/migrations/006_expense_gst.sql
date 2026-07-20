-- GST breakdown on expense records
-- Run once on existing databases (or use /install.php?migrate_expenses=1)

ALTER TABLE expenses
  ADD COLUMN IF NOT EXISTS gst_applicable TINYINT(1) NOT NULL DEFAULT 0 AFTER amount,
  ADD COLUMN IF NOT EXISTS cgst_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER gst_applicable,
  ADD COLUMN IF NOT EXISTS sgst_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER cgst_amount,
  ADD COLUMN IF NOT EXISTS total_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER sgst_amount;

UPDATE expenses SET total_amount = amount WHERE total_amount = 0 OR total_amount IS NULL;
