-- Bank transaction ledger + optional links on sell orders and purchase orders

CREATE TABLE IF NOT EXISTS bank_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bank_account_id INT UNSIGNED NOT NULL,
  transaction_type ENUM('credit','debit') NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  balance_after DECIMAL(15,2) NOT NULL,
  reference_type ENUM('manual','opening_balance','sell_order','purchase_order','adjustment') NOT NULL DEFAULT 'manual',
  reference_id INT UNSIGNED NULL,
  description VARCHAR(255) NOT NULL,
  transaction_date DATE NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_bank_tx_account (bank_account_id),
  INDEX idx_bank_tx_ref (reference_type, reference_id),
  INDEX idx_bank_tx_date (transaction_date)
) ENGINE=InnoDB;

ALTER TABLE orders
  ADD COLUMN bank_account_id INT UNSIGNED NULL AFTER amount_due,
  ADD COLUMN affect_bank_balance TINYINT(1) NOT NULL DEFAULT 0 AFTER bank_account_id,
  ADD CONSTRAINT fk_orders_bank_account FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id);

ALTER TABLE purchase_orders
  ADD COLUMN payment_status ENUM('unpaid','full','partial') NOT NULL DEFAULT 'unpaid' AFTER total_amount,
  ADD COLUMN amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER payment_status,
  ADD COLUMN amount_due DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER amount_paid,
  ADD COLUMN bank_account_id INT UNSIGNED NULL AFTER amount_due,
  ADD COLUMN affect_bank_balance TINYINT(1) NOT NULL DEFAULT 0 AFTER bank_account_id,
  ADD CONSTRAINT fk_po_bank_account FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id);
