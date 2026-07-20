-- SK Mobility ERP — Full Database Schema (MySQL 8)
-- Import this file first in phpMyAdmin, then seed.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS sk_mobility CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sk_mobility;

-- ============================================================
-- USERS & AUTH
-- ============================================================

CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(50) NOT NULL UNIQUE,
  description TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  module VARCHAR(50) NOT NULL,
  description TEXT NULL
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NOT NULL,
  email VARCHAR(191) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  phone VARCHAR(20) NULL,
  avatar_url VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  reset_token VARCHAR(100) NULL,
  reset_token_expires DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE refresh_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  is_revoked TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- DEALERS
-- ============================================================

CREATE TABLE dealers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  business_name VARCHAR(200) NOT NULL,
  contact_person VARCHAR(150) NOT NULL,
  email VARCHAR(191) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  gst_number VARCHAR(30) NULL,
  pan_number VARCHAR(20) NULL,
  dealer_code VARCHAR(30) NULL UNIQUE,
  status ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  performance_score INT NOT NULL DEFAULT 0,
  total_orders INT NOT NULL DEFAULT 0,
  total_revenue DECIMAL(15,2) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE dealer_addresses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dealer_id INT UNSIGNED NOT NULL,
  address_line1 VARCHAR(255) NOT NULL,
  address_line2 VARCHAR(255) NULL,
  city VARCHAR(100) NOT NULL,
  state VARCHAR(100) NOT NULL,
  pincode VARCHAR(10) NOT NULL,
  country VARCHAR(50) NOT NULL DEFAULT 'India',
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (dealer_id) REFERENCES dealers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE dealer_documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dealer_id INT UNSIGNED NOT NULL,
  document_type ENUM('gst','pan','aadhar','bank','license','other') NOT NULL,
  file_url VARCHAR(255) NOT NULL,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (dealer_id) REFERENCES dealers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- VEHICLES
-- ============================================================

CREATE TABLE vehicle_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE vehicles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(150) NOT NULL UNIQUE,
  brand VARCHAR(100) NOT NULL DEFAULT 'SK Mobility',
  description TEXT NULL,
  base_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  avg_rating DECIMAL(3,2) NOT NULL DEFAULT 0,
  review_count INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES vehicle_categories(id)
) ENGINE=InnoDB;

CREATE TABLE vehicle_variants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  sku VARCHAR(50) NOT NULL UNIQUE,
  color VARCHAR(50) NULL,
  price DECIMAL(12,2) NOT NULL,
  battery_capacity_kwh DECIMAL(5,2) NULL,
  battery_type ENUM('Lithium Ion','Lead Acid') NULL,
  battery_spec VARCHAR(100) NULL,
  range_km INT NULL,
  specifications JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE vehicle_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED NULL,
  image_url VARCHAR(255) NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
  FOREIGN KEY (variant_id) REFERENCES vehicle_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE vehicle_reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  dealer_id INT UNSIGNED NULL,
  rating TINYINT NOT NULL,
  title VARCHAR(150) NULL,
  review_text TEXT NULL,
  is_approved TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (dealer_id) REFERENCES dealers(id) ON DELETE SET NULL,
  CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- ============================================================
-- ORDERS
-- ============================================================

CREATE TABLE orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(40) NOT NULL UNIQUE,
  booking_no VARCHAR(50) NULL,
  order_type ENUM('dealer','customer') NOT NULL,
  product_type ENUM('vehicle','spare_part') NOT NULL DEFAULT 'vehicle',
  billing_location ENUM('kokamthan','kopargaon') NOT NULL DEFAULT 'kokamthan',
  dealer_id INT UNSIGNED NULL,
  customer_name VARCHAR(150) NULL,
  customer_phone VARCHAR(20) NULL,
  customer_email VARCHAR(191) NULL,
  customer_address TEXT NULL,
  customer_aadhaar VARCHAR(20) NULL,
  customer_pan VARCHAR(20) NULL,
  chassis_no VARCHAR(50) NULL,
  motor_no VARCHAR(50) NULL,
  battery_capacity VARCHAR(50) NULL,
  battery_no VARCHAR(80) NULL,
  controller_no VARCHAR(80) NULL,
  charger_no VARCHAR(80) NULL,
  motor_warranty VARCHAR(80) NULL,
  battery_warranty VARCHAR(80) NULL,
  controller_warranty VARCHAR(80) NULL,
  charger_warranty VARCHAR(80) NULL,
  hp_name VARCHAR(150) NULL,
  color VARCHAR(50) NULL,
  vehicle_model_type VARCHAR(100) NULL,
  pm_drive_incentive DECIMAL(10,2) NOT NULL DEFAULT 0,
  state_subsidy DECIMAL(10,2) NOT NULL DEFAULT 0,
  loan_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_mode VARCHAR(40) NULL,
  payment_status ENUM('full','partial') NOT NULL DEFAULT 'full',
  amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount_due DECIMAL(12,2) NOT NULL DEFAULT 0,
  sale_date DATE NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  cgst_rate DECIMAL(5,2) NOT NULL DEFAULT 14,
  sgst_rate DECIMAL(5,2) NOT NULL DEFAULT 14,
  tax_rate DECIMAL(5,2) NOT NULL DEFAULT 28,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('pending','approved','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  delivery_address TEXT NULL,
  tracking_number VARCHAR(100) NULL,
  notes TEXT NULL,
  expected_delivery_date DATE NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (dealer_id) REFERENCES dealers(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  item_type ENUM('vehicle_variant','spare_part') NOT NULL DEFAULT 'vehicle_variant',
  vehicle_id INT UNSIGNED NULL,
  variant_id INT UNSIGNED NULL,
  spare_part_id INT UNSIGNED NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  total_price DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
  FOREIGN KEY (variant_id) REFERENCES vehicle_variants(id),
  FOREIGN KEY (spare_part_id) REFERENCES spare_parts(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_status_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  status VARCHAR(30) NOT NULL,
  notes TEXT NULL,
  changed_by INT UNSIGNED NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (changed_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- PAYMENTS
-- ============================================================

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  dealer_id INT UNSIGNED NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_method ENUM('cash','bank_transfer','cheque','online','razorpay') NOT NULL,
  payment_date DATE NOT NULL,
  transaction_reference VARCHAR(100) NULL,
  razorpay_order_id VARCHAR(100) NULL,
  razorpay_payment_id VARCHAR(100) NULL,
  status ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (dealer_id) REFERENCES dealers(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- BILLING
-- ============================================================

CREATE TABLE bills (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bill_number VARCHAR(40) NOT NULL UNIQUE,
  bill_type ENUM('vehicle','warranty','spare') NOT NULL DEFAULT 'vehicle',
  billing_location ENUM('kokamthan','kopargaon') NOT NULL DEFAULT 'kokamthan',
  order_id INT UNSIGNED NULL,
  booking_no VARCHAR(50) NULL,
  company_name VARCHAR(200) NULL,
  company_address TEXT NULL,
  company_branch_address TEXT NULL,
  company_phone VARCHAR(60) NULL,
  company_email VARCHAR(191) NULL,
  company_gstin VARCHAR(30) NULL,
  company_state VARCHAR(80) NULL,
  company_state_code VARCHAR(5) NULL,
  brand_name VARCHAR(100) NULL,
  dealer_code VARCHAR(30) NULL,
  customer_name VARCHAR(150) NULL,
  customer_phone VARCHAR(20) NULL,
  customer_email VARCHAR(191) NULL,
  customer_address TEXT NULL,
  customer_city VARCHAR(100) NULL,
  customer_state VARCHAR(100) NULL,
  customer_state_code VARCHAR(5) NULL,
  customer_aadhaar VARCHAR(20) NULL,
  customer_pan VARCHAR(20) NULL,
  vehicle_model VARCHAR(150) NULL,
  vehicle_model_type VARCHAR(100) NULL,
  color VARCHAR(50) NULL,
  chassis_no VARCHAR(50) NULL,
  motor_no VARCHAR(50) NULL,
  battery_type_no VARCHAR(120) NULL,
  controller_no VARCHAR(80) NULL,
  charger_no VARCHAR(80) NULL,
  motor_warranty VARCHAR(80) NULL,
  battery_warranty VARCHAR(80) NULL,
  controller_warranty VARCHAR(80) NULL,
  charger_warranty VARCHAR(80) NULL,
  hp_name VARCHAR(150) NULL,
  registration_no VARCHAR(50) NULL,
  odometer_reading INT NULL,
  vehicle_sale_date DATE NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_rate DECIMAL(5,2) NOT NULL DEFAULT 28,
  cgst_rate DECIMAL(5,2) NOT NULL DEFAULT 14,
  sgst_rate DECIMAL(5,2) NOT NULL DEFAULT 14,
  pm_drive_incentive DECIMAL(10,2) NOT NULL DEFAULT 0,
  state_subsidy DECIMAL(10,2) NOT NULL DEFAULT 0,
  loan_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_mode VARCHAR(40) NULL,
  payment_status ENUM('full','partial') NOT NULL DEFAULT 'full',
  amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount_due DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  warranty_start DATE NULL,
  warranty_end DATE NULL,
  warranty_period VARCHAR(50) NULL,
  notes TEXT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE bill_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bill_id INT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  model_code VARCHAR(80) NULL,
  hsn_code VARCHAR(20) NOT NULL DEFAULT '87116020',
  quantity INT NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  taxable_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  cgst_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  sgst_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_price DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE taxes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  rate DECIMAL(5,2) NOT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE system_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  description VARCHAR(255) NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- INVENTORY
-- ============================================================

CREATE TABLE warehouses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  location VARCHAR(150) NULL,
  address TEXT NULL,
  manager_name VARCHAR(150) NULL,
  phone VARCHAR(20) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE inventory (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL,
  quantity_available INT NOT NULL DEFAULT 0,
  quantity_reserved INT NOT NULL DEFAULT 0,
  min_stock_level INT NOT NULL DEFAULT 5,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_variant_warehouse (variant_id, warehouse_id),
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
  FOREIGN KEY (variant_id) REFERENCES vehicle_variants(id),
  FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB;

CREATE TABLE inventory_movements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  variant_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL,
  movement_type ENUM('stock_in','stock_out','transfer_in','transfer_out','adjustment') NOT NULL,
  quantity INT NOT NULL,
  reference_id INT UNSIGNED NULL,
  notes TEXT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (variant_id) REFERENCES vehicle_variants(id),
  FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE purchase_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  po_number VARCHAR(40) NOT NULL UNIQUE,
  product_type ENUM('vehicle','spare_part') NOT NULL DEFAULT 'vehicle',
  supplier_name VARCHAR(200) NULL,
  po_date DATE NOT NULL,
  supplier_invoice_no VARCHAR(80) NULL,
  supplier_invoice_date DATE NULL,
  status ENUM('draft','confirmed','partial','received','cancelled') NOT NULL DEFAULT 'draft',
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  gst_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE purchase_order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_order_id INT UNSIGNED NOT NULL,
  item_type ENUM('vehicle_variant','spare_part') NOT NULL DEFAULT 'vehicle_variant',
  vehicle_id INT UNSIGNED NULL,
  variant_id INT UNSIGNED NULL,
  spare_part_id INT UNSIGNED NULL,
  color VARCHAR(50) NULL,
  hsn_code VARCHAR(20) NOT NULL DEFAULT '87116020',
  description VARCHAR(255) NULL,
  quantity_ordered INT NOT NULL,
  quantity_received INT NOT NULL DEFAULT 0,
  unit_rate DECIMAL(12,2) NOT NULL,
  gst_percent DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  taxable_value DECIMAL(14,2) NOT NULL DEFAULT 0,
  gst_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
  FOREIGN KEY (variant_id) REFERENCES vehicle_variants(id),
  FOREIGN KEY (spare_part_id) REFERENCES spare_parts(id) ON DELETE SET NULL,
  INDEX idx_po_items_po (purchase_order_id)
) ENGINE=InnoDB;

CREATE TABLE purchase_order_receipts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_order_id INT UNSIGNED NOT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes TEXT NULL,
  created_by INT UNSIGNED NOT NULL,
  FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE purchase_order_receipt_lines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receipt_id INT UNSIGNED NOT NULL,
  po_item_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NULL,
  quantity INT NOT NULL,
  FOREIGN KEY (receipt_id) REFERENCES purchase_order_receipts(id) ON DELETE CASCADE,
  FOREIGN KEY (po_item_id) REFERENCES purchase_order_items(id) ON DELETE CASCADE,
  FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB;

-- ============================================================
-- LEADS
-- ============================================================

CREATE TABLE lead_sources (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE leads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_name VARCHAR(150) NOT NULL,
  customer_phone VARCHAR(20) NOT NULL,
  customer_email VARCHAR(191) NULL,
  source_id INT UNSIGNED NULL,
  interested_vehicle_id INT UNSIGNED NULL,
  status ENUM('new','contacted','qualified','converted','lost') NOT NULL DEFAULT 'new',
  notes TEXT NULL,
  assigned_to INT UNSIGNED NULL,
  dealer_id INT UNSIGNED NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (source_id) REFERENCES lead_sources(id) ON DELETE SET NULL,
  FOREIGN KEY (interested_vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (dealer_id) REFERENCES dealers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE lead_followups (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id INT UNSIGNED NOT NULL,
  note TEXT NOT NULL,
  follow_up_date DATE NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- SERVICE
-- ============================================================

CREATE TABLE technicians (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(20) NULL,
  email VARCHAR(191) NULL,
  specialization VARCHAR(150) NULL,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE service_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_number VARCHAR(40) NOT NULL UNIQUE,
  customer_name VARCHAR(150) NOT NULL,
  customer_phone VARCHAR(20) NOT NULL,
  vehicle_model VARCHAR(150) NULL,
  vehicle_vin VARCHAR(50) NULL,
  issue_description TEXT NOT NULL,
  status ENUM('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  dealer_id INT UNSIGNED NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (dealer_id) REFERENCES dealers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE job_cards (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_card_number VARCHAR(40) NOT NULL UNIQUE,
  service_request_id INT UNSIGNED NOT NULL,
  technician_id INT UNSIGNED NULL,
  work_description TEXT NULL,
  parts_used TEXT NULL,
  labour_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
  parts_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('open','in_progress','completed') NOT NULL DEFAULT 'open',
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (service_request_id) REFERENCES service_requests(id),
  FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- SPARE PARTS
-- ============================================================

CREATE TABLE spare_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE spare_parts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  part_number VARCHAR(50) NOT NULL UNIQUE,
  description TEXT NULL,
  unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  quantity_in_stock INT NOT NULL DEFAULT 0,
  min_stock_level INT NOT NULL DEFAULT 5,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES spare_categories(id)
) ENGINE=InnoDB;

CREATE TABLE spare_parts_usage (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  spare_part_id INT UNSIGNED NOT NULL,
  job_card_id INT UNSIGNED NULL,
  service_request_id INT UNSIGNED NULL,
  quantity_used INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  total_price DECIMAL(10,2) NOT NULL,
  notes TEXT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (spare_part_id) REFERENCES spare_parts(id),
  FOREIGN KEY (job_card_id) REFERENCES job_cards(id) ON DELETE SET NULL,
  FOREIGN KEY (service_request_id) REFERENCES service_requests(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- HR
-- ============================================================

CREATE TABLE employees (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_code VARCHAR(30) NOT NULL UNIQUE,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(191) NULL,
  phone VARCHAR(20) NULL,
  department VARCHAR(100) NULL,
  designation VARCHAR(100) NULL,
  date_of_joining DATE NULL,
  basic_salary DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('active','inactive','terminated') NOT NULL DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE salary_records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  month TINYINT NOT NULL,
  year SMALLINT NOT NULL,
  basic_salary DECIMAL(10,2) NOT NULL,
  allowances DECIMAL(10,2) NOT NULL DEFAULT 0,
  deductions DECIMAL(10,2) NOT NULL DEFAULT 0,
  net_salary DECIMAL(10,2) NOT NULL,
  payment_date DATE NULL,
  payment_mode ENUM('bank','cash','cheque') NULL,
  notes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  CHECK (month BETWEEN 1 AND 12)
) ENGINE=InnoDB;

-- ============================================================
-- PARTNERS
-- ============================================================

CREATE TABLE partners (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  type ENUM('vendor','distributor','supplier','other') NOT NULL DEFAULT 'vendor',
  contact_person VARCHAR(150) NULL,
  phone VARCHAR(20) NULL,
  email VARCHAR(191) NULL,
  address TEXT NULL,
  aadhar_number VARCHAR(20) NULL,
  pan_number VARCHAR(20) NULL,
  gst_number VARCHAR(30) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE partner_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_id INT UNSIGNED NOT NULL,
  transaction_type ENUM('payment','receipt','adjustment') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  date DATE NOT NULL,
  description TEXT NULL,
  reference_number VARCHAR(100) NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- EXPENSES
-- ============================================================

CREATE TABLE expense_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE expenses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  record_type ENUM('asset','expenditure') NOT NULL DEFAULT 'expenditure',
  name VARCHAR(150) NOT NULL DEFAULT '',
  amount DECIMAL(10,2) NOT NULL,
  gst_applicable TINYINT(1) NOT NULL DEFAULT 0,
  cgst_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  sgst_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  description TEXT NULL,
  expense_date DATE NOT NULL,
  payment_mode ENUM('cash','bank','upi','card','cheque') NOT NULL DEFAULT 'cash',
  receipt_url VARCHAR(255) NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES expense_categories(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE expense_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  expense_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE,
  INDEX idx_expense_items_expense (expense_id)
) ENGINE=InnoDB;

-- ============================================================
-- FINANCE
-- ============================================================

CREATE TABLE bank_accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_name VARCHAR(150) NOT NULL,
  bank_name VARCHAR(150) NOT NULL,
  account_number VARCHAR(50) NOT NULL,
  ifsc_code VARCHAR(20) NULL,
  account_type ENUM('current','savings','overdraft') NOT NULL DEFAULT 'current',
  current_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE loans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lender_name VARCHAR(200) NOT NULL,
  loan_type ENUM('vehicle','equipment','personal','business','other') NOT NULL DEFAULT 'business',
  principal_amount DECIMAL(15,2) NOT NULL,
  interest_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  tenure_months INT NOT NULL DEFAULT 0,
  emi_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  start_date DATE NULL,
  end_date DATE NULL,
  outstanding_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  status ENUM('active','closed','defaulted') NOT NULL DEFAULT 'active',
  notes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- NOTIFICATIONS & AUDIT
-- ============================================================

CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  type VARCHAR(50) NULL,
  entity_type VARCHAR(50) NULL,
  entity_id INT UNSIGNED NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action ENUM('create','update','delete','view','login','logout') NOT NULL,
  module VARCHAR(50) NOT NULL,
  entity_type VARCHAR(50) NULL,
  entity_id INT UNSIGNED NULL,
  old_values JSON NULL,
  new_values JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
