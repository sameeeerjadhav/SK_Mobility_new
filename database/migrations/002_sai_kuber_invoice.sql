-- SAI KUBER MOBILITY tax invoice fields
-- Run once on existing databases (or use /install.php?migrate_invoice=1)

ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS booking_no VARCHAR(50) NULL AFTER order_number,
  ADD COLUMN IF NOT EXISTS vehicle_model_type VARCHAR(100) NULL AFTER color,
  ADD COLUMN IF NOT EXISTS battery_no VARCHAR(80) NULL AFTER battery_capacity,
  ADD COLUMN IF NOT EXISTS controller_no VARCHAR(80) NULL AFTER battery_no,
  ADD COLUMN IF NOT EXISTS charger_no VARCHAR(80) NULL AFTER controller_no,
  ADD COLUMN IF NOT EXISTS motor_warranty VARCHAR(80) NULL AFTER charger_no,
  ADD COLUMN IF NOT EXISTS battery_warranty VARCHAR(80) NULL AFTER motor_warranty,
  ADD COLUMN IF NOT EXISTS controller_warranty VARCHAR(80) NULL AFTER battery_warranty,
  ADD COLUMN IF NOT EXISTS charger_warranty VARCHAR(80) NULL AFTER controller_warranty,
  ADD COLUMN IF NOT EXISTS hp_name VARCHAR(150) NULL AFTER charger_warranty,
  ADD COLUMN IF NOT EXISTS loan_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER state_subsidy,
  ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER loan_amount,
  ADD COLUMN IF NOT EXISTS payment_mode VARCHAR(40) NULL AFTER discount_amount,
  ADD COLUMN IF NOT EXISTS sale_date DATE NULL AFTER payment_mode;

ALTER TABLE bills
  ADD COLUMN IF NOT EXISTS booking_no VARCHAR(50) NULL AFTER order_id,
  ADD COLUMN IF NOT EXISTS company_branch_address TEXT NULL AFTER company_address,
  ADD COLUMN IF NOT EXISTS company_state VARCHAR(80) NULL AFTER company_gstin,
  ADD COLUMN IF NOT EXISTS customer_email VARCHAR(191) NULL AFTER customer_phone,
  ADD COLUMN IF NOT EXISTS vehicle_model_type VARCHAR(100) NULL AFTER vehicle_model,
  ADD COLUMN IF NOT EXISTS color VARCHAR(50) NULL AFTER vehicle_model_type,
  ADD COLUMN IF NOT EXISTS battery_type_no VARCHAR(120) NULL AFTER motor_no,
  ADD COLUMN IF NOT EXISTS controller_no VARCHAR(80) NULL AFTER battery_type_no,
  ADD COLUMN IF NOT EXISTS charger_no VARCHAR(80) NULL AFTER controller_no,
  ADD COLUMN IF NOT EXISTS motor_warranty VARCHAR(80) NULL AFTER charger_no,
  ADD COLUMN IF NOT EXISTS battery_warranty VARCHAR(80) NULL AFTER motor_warranty,
  ADD COLUMN IF NOT EXISTS controller_warranty VARCHAR(80) NULL AFTER battery_warranty,
  ADD COLUMN IF NOT EXISTS charger_warranty VARCHAR(80) NULL AFTER controller_warranty,
  ADD COLUMN IF NOT EXISTS hp_name VARCHAR(150) NULL AFTER charger_warranty,
  ADD COLUMN IF NOT EXISTS loan_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER state_subsidy,
  ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER loan_amount,
  ADD COLUMN IF NOT EXISTS payment_mode VARCHAR(40) NULL AFTER discount_amount;

ALTER TABLE bill_items
  ADD COLUMN IF NOT EXISTS model_code VARCHAR(80) NULL AFTER description,
  ADD COLUMN IF NOT EXISTS discount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER unit_price,
  ADD COLUMN IF NOT EXISTS taxable_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER discount,
  ADD COLUMN IF NOT EXISTS cgst_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER taxable_amount,
  ADD COLUMN IF NOT EXISTS sgst_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER cgst_amount;

INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('company_name', 'SAI KUBER MOBILITY', 'Legal company name'),
('company_address', 'Main Branch: S.No. 365, Opp. to Atma Malik Hospital, Nagar Manmad Road, Kokamthan.', 'Main branch address'),
('company_branch_address', 'Branch: Opp. Rajpal, Nagar-Manmad Road, Tal. Kopargaon, Dist. Ahilyanagar.', 'Second branch address'),
('company_phone', '9130119191, 9270047343', 'Company phones'),
('company_email', 'info@saikubermobility.com', 'Company email'),
('company_gstin', '27AFZFS1183A1ZP', 'GSTIN'),
('company_state', 'Maharashtra', 'State name'),
('brand_name', 'SK MOBILITY', 'Brand / logo text'),
('company_state_code', '27', 'Maharashtra state code')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description);
