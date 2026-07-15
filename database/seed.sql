-- SK Mobility ERP — Seed Data
-- Import AFTER schema.sql

USE sk_mobility;

-- Roles
INSERT INTO roles (id, name, slug, description) VALUES
(1, 'Super Admin', 'super_admin', 'Full platform access'),
(2, 'Dealer', 'dealer', 'Dealer portal access'),
(3, 'Service', 'service', 'Service & spare parts'),
(4, 'HR', 'hr', 'HR module (restricted)'),
(5, 'Accountant', 'accountant', 'Payments, billing, reports');

-- Permissions
INSERT INTO permissions (id, name, slug, module, description) VALUES
(1,  'View Dashboard', 'view_dashboard', 'dashboard', NULL),
(2,  'Manage Dealers', 'manage_dealers', 'dealers', NULL),
(3,  'View Orders', 'view_orders', 'orders', NULL),
(4,  'Manage Orders', 'manage_orders', 'orders', NULL),
(5,  'Approve Orders', 'approve_orders', 'orders', NULL),
(6,  'View Payments', 'view_payments', 'payments', NULL),
(7,  'Manage Payments', 'manage_payments', 'payments', NULL),
(8,  'View Billing', 'view_billing', 'billing', NULL),
(9,  'Manage Billing', 'manage_billing', 'billing', NULL),
(10, 'View Inventory', 'view_inventory', 'inventory', NULL),
(11, 'Manage Inventory', 'manage_inventory', 'inventory', NULL),
(12, 'View Leads', 'view_leads', 'leads', NULL),
(13, 'Manage Leads', 'manage_leads', 'leads', NULL),
(14, 'View Services', 'view_services', 'services', NULL),
(15, 'Manage Services', 'manage_services', 'services', NULL),
(16, 'View Spare Parts', 'view_spare_parts', 'spare_parts', NULL),
(17, 'Manage Spare Parts', 'manage_spare_parts', 'spare_parts', NULL),
(18, 'View Vehicles', 'view_vehicles', 'vehicles', NULL),
(19, 'Manage Vehicles', 'manage_vehicles', 'vehicles', NULL),
(20, 'Manage Users', 'manage_users', 'admin', NULL),
(21, 'Manage Roles', 'manage_roles', 'admin', NULL),
(22, 'View Audit Logs', 'view_audit_logs', 'admin', NULL),
(23, 'Manage Settings', 'manage_settings', 'admin', NULL),
(24, 'Export Reports', 'export_reports', 'reports', NULL),
(25, 'View Reports', 'view_reports', 'reports', NULL);

-- Super Admin: all permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Dealer permissions
INSERT INTO role_permissions (role_id, permission_id) VALUES
(2, 1), (2, 3), (2, 4), (2, 6), (2, 12), (2, 13), (2, 14), (2, 18);

-- Service permissions
INSERT INTO role_permissions (role_id, permission_id) VALUES
(3, 1), (3, 14), (3, 15), (3, 16), (3, 17);

-- Accountant permissions
INSERT INTO role_permissions (role_id, permission_id) VALUES
(5, 1), (5, 6), (5, 8), (5, 24), (5, 25);

-- Default admin: email admin@skmobility.com / password Admin@123
INSERT INTO users (id, role_id, email, password_hash, first_name, last_name, phone, is_active, is_verified) VALUES
(1, 1, 'admin@skmobility.com', '$2y$10$8hQP4OmQjFP8gg6HPHmjveWbaRB/ELddTSwft6XxWErDKdwH.HMgu', 'Super', 'Admin', '9999999999', 1, 1);

-- System settings (SAI KUBER MOBILITY tax invoice header)
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('company_name', 'SAI KUBER MOBILITY', 'Legal company name on tax invoice'),
('company_address', 'Main Branch: S.No. 365, Opp. to Atma Malik Hospital, Nagar Manmad Road, Kokamthan.', 'Main branch address'),
('company_branch_address', 'Branch: Opp. Rajpal, Nagar-Manmad Road, Tal. Kopargaon, Dist. Ahilyanagar.', 'Second branch address'),
('company_phone', '9130119191, 9270047343', 'Company phones'),
('company_email', 'info@saikubermobility.com', 'Company email'),
('company_gstin', '27AFZFS1183A1ZP', 'GSTIN'),
('company_state', 'Maharashtra', 'State name on invoice'),
('brand_name', 'SK MOBILITY', 'Brand / logo text'),
('company_state_code', '27', 'Maharashtra state code');

-- Taxes
INSERT INTO taxes (name, rate, description, is_active) VALUES
('CGST', 14.00, 'Central GST for EVs', 1),
('SGST', 14.00, 'State GST for EVs', 1),
('IGST', 28.00, 'Integrated GST for EVs', 1);

-- Vehicle categories
INSERT INTO vehicle_categories (name, slug, description, sort_order, is_active) VALUES
('Electric Scooter', 'electric-scooter', '2-wheeler EV scooters', 1, 1),
('Electric Motorcycle', 'electric-motorcycle', '2-wheeler EV bikes', 2, 1),
('Electric Rickshaw', 'electric-rickshaw', '3-wheeler passenger EVs', 3, 1),
('Electric Cargo', 'electric-cargo', 'Commercial cargo EVs', 4, 1);

-- Lead sources
INSERT INTO lead_sources (name, is_active) VALUES
('Website', 1),
('Walk-in', 1),
('Referral', 1),
('Social Media', 1),
('Campaign', 1);

-- Warehouses
INSERT INTO warehouses (name, location, address, manager_name, phone, is_active) VALUES
('Main Warehouse', 'Pune', 'Plot 12, MIDC, Pune', 'Warehouse Manager', '9876500001', 1),
('North Hub', 'Delhi NCR', 'Sector 63, Noida', 'North Manager', '9876500002', 1);

-- Expense categories
INSERT INTO expense_categories (name, description, is_active) VALUES
('Rent', 'Office & warehouse rent', 1),
('Utilities', 'Electricity, water, internet', 1),
('Travel', 'Staff travel expenses', 1),
('Marketing', 'Ads and promotions', 1),
('Miscellaneous', 'Other office expenses', 1);

-- Spare categories
INSERT INTO spare_categories (name, description, is_active) VALUES
('Battery', 'Battery packs and cells', 1),
('Motor', 'Drive motors', 1),
('Controller', 'Motor controllers', 1),
('Body Parts', 'Body and chassis parts', 1),
('Accessories', 'Chargers and accessories', 1);

-- Sample vehicle for quick testing
INSERT INTO vehicles (id, category_id, name, slug, brand, description, base_price, is_active) VALUES
(1, 1, 'SK Spark 3.0', 'sk-spark-3-0', 'SK Mobility', 'Urban electric scooter with long range.', 89999.00, 1);

INSERT INTO vehicle_variants (vehicle_id, name, sku, color, price, battery_capacity_kwh, range_km, is_active) VALUES
(1, 'Standard Red', 'SPARK-STD-RED', 'Red', 89999.00, 2.5, 110, 1),
(1, 'Pro Black', 'SPARK-PRO-BLK', 'Black', 99999.00, 3.2, 140, 1);
