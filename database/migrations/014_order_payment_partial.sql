ALTER TABLE orders
  ADD COLUMN payment_status ENUM('full','partial') NOT NULL DEFAULT 'full' AFTER payment_mode,
  ADD COLUMN amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER payment_status,
  ADD COLUMN amount_due DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER amount_paid;

ALTER TABLE bills
  ADD COLUMN payment_status ENUM('full','partial') NOT NULL DEFAULT 'full' AFTER payment_mode,
  ADD COLUMN amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER payment_status,
  ADD COLUMN amount_due DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER amount_paid;
