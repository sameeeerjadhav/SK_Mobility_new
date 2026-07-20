<?php
/**
 * One-time installer / migrator for Hostinger.
 * Open after deploy, then delete this file.
 *
 *  /install.php
 *  /install.php?reset_admin=1
 *  /install.php?migrate_invoice=1   ← SAI KUBER tax invoice columns + company settings
 *  /install.php?migrate_expenses=1  ← expense types, GST, multi-item line items
 *  /install.php?migrate_partners=1  ← partner Aadhar & PAN fields
 *  /install.php?migrate_purchase_orders=1  ← purchase orders + goods receipt tables
 *  /install.php?migrate_po_supplier=1  ← supplier company name on purchase orders (replaces partners link)
 *  /install.php?migrate_po_spare_items=1  ← spare parts / batteries on PO line items
 *  /install.php?migrate_billing_location=1  ← Kokamthan / Kopargaon billing location on orders & invoices
 *  /install.php?migrate_sell_order_spare_parts=1  ← spare parts on sell orders + order line types
 *  /install.php?migrate_order_payment_partial=1  ← full vs partial payment on sell orders & invoices
 *  /install.php?migrate_sell_order_gst=1  ← custom GST rates stored on sell orders
 *  /install.php?migrate_po_product_type=1  ← vehicle vs spare parts on purchase orders
 *  /install.php?migrate_po_gst=1  ← custom GST rates on purchase orders
 *  /install.php?migrate_bank_transactions=1  ← bank ledger + order/PO bank links
 *  /install.php?migrate_performance=1  ← speed indexes for dashboard, lists, notifications
 */
require dirname(__DIR__) . '/app/Config/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

echo "PHP " . PHP_VERSION . "\n";
echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? 'yes' : 'NO') . "\n";

try {
    $db = \App\Core\Database::connection();
    echo "DB connection: OK\n";
    $count = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    echo "Users in DB: {$count}\n";

    if (isset($_GET['reset_admin']) && $_GET['reset_admin'] === '1') {
        $hash = password_hash('Admin@123', PASSWORD_BCRYPT);
        $db->prepare('UPDATE users SET password_hash = ?, is_active = 1 WHERE email = ?')
            ->execute([$hash, 'admin@skmobility.com']);
        echo "Admin password reset to Admin@123\n";
    }

    if (isset($_GET['migrate_invoice']) && $_GET['migrate_invoice'] === '1') {
        echo "\n--- Tax invoice migration ---\n";

        $addCol = static function (PDO $db, string $table, string $column, string $definition): void {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            if ((int)$stmt->fetchColumn() > 0) {
                echo "skip {$table}.{$column}\n";
                return;
            }
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            echo "added {$table}.{$column}\n";
        };

        $addCol($db, 'orders', 'booking_no', 'VARCHAR(50) NULL AFTER order_number');
        $addCol($db, 'orders', 'vehicle_model_type', 'VARCHAR(100) NULL');
        $addCol($db, 'orders', 'battery_no', 'VARCHAR(80) NULL');
        $addCol($db, 'orders', 'controller_no', 'VARCHAR(80) NULL');
        $addCol($db, 'orders', 'charger_no', 'VARCHAR(80) NULL');
        $addCol($db, 'orders', 'motor_warranty', 'VARCHAR(80) NULL');
        $addCol($db, 'orders', 'battery_warranty', 'VARCHAR(80) NULL');
        $addCol($db, 'orders', 'controller_warranty', 'VARCHAR(80) NULL');
        $addCol($db, 'orders', 'charger_warranty', 'VARCHAR(80) NULL');
        $addCol($db, 'orders', 'hp_name', 'VARCHAR(150) NULL');
        $addCol($db, 'orders', 'loan_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $addCol($db, 'orders', 'discount_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $addCol($db, 'orders', 'payment_mode', 'VARCHAR(40) NULL');
        $addCol($db, 'orders', 'sale_date', 'DATE NULL');

        $addCol($db, 'bills', 'booking_no', 'VARCHAR(50) NULL AFTER order_id');
        $addCol($db, 'bills', 'company_branch_address', 'TEXT NULL AFTER company_address');
        $addCol($db, 'bills', 'company_state', 'VARCHAR(80) NULL AFTER company_gstin');
        $addCol($db, 'bills', 'customer_email', 'VARCHAR(191) NULL AFTER customer_phone');
        $addCol($db, 'bills', 'vehicle_model_type', 'VARCHAR(100) NULL AFTER vehicle_model');
        $addCol($db, 'bills', 'color', 'VARCHAR(50) NULL AFTER vehicle_model_type');
        $addCol($db, 'bills', 'battery_type_no', 'VARCHAR(120) NULL AFTER motor_no');
        $addCol($db, 'bills', 'controller_no', 'VARCHAR(80) NULL');
        $addCol($db, 'bills', 'charger_no', 'VARCHAR(80) NULL');
        $addCol($db, 'bills', 'motor_warranty', 'VARCHAR(80) NULL');
        $addCol($db, 'bills', 'battery_warranty', 'VARCHAR(80) NULL');
        $addCol($db, 'bills', 'controller_warranty', 'VARCHAR(80) NULL');
        $addCol($db, 'bills', 'charger_warranty', 'VARCHAR(80) NULL');
        $addCol($db, 'bills', 'hp_name', 'VARCHAR(150) NULL');
        $addCol($db, 'bills', 'loan_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $addCol($db, 'bills', 'discount_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $addCol($db, 'bills', 'payment_mode', 'VARCHAR(40) NULL');

        $addCol($db, 'bill_items', 'model_code', 'VARCHAR(80) NULL AFTER description');
        $addCol($db, 'bill_items', 'discount', 'DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER unit_price');
        $addCol($db, 'bill_items', 'taxable_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER discount');
        $addCol($db, 'bill_items', 'cgst_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER taxable_amount');
        $addCol($db, 'bill_items', 'sgst_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER cgst_amount');

        $settings = [
            'company_name' => ['SAI KUBER MOBILITY', 'Legal company name on tax invoice'],
            'company_address' => ['Main Branch: S.No. 365, Opp. to Atma Malik Hospital, Nagar Manmad Road, Kokamthan.', 'Main branch address'],
            'company_branch_address' => ['Branch: Opp. Rajpal, Nagar-Manmad Road, Tal. Kopargaon, Dist. Ahilyanagar.', 'Second branch address'],
            'company_phone' => ['9130119191, 9270047343', 'Company phones'],
            'company_email' => ['info@saikubermobility.com', 'Company email'],
            'company_gstin' => ['27AFZFS1183A1ZP', 'GSTIN'],
            'company_state' => ['Maharashtra', 'State name on invoice'],
            'brand_name' => ['SK MOBILITY', 'Brand / logo text'],
            'company_state_code' => ['27', 'Maharashtra state code'],
        ];
        $upsert = $db->prepare(
            'INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description)'
        );
        foreach ($settings as $key => [$value, $desc]) {
            $upsert->execute([$key, $value, $desc]);
            echo "setting {$key}\n";
        }

        // Stamp company snapshot on existing bills that still have old branding
        $db->exec(
            "UPDATE bills SET
                company_name = 'SAI KUBER MOBILITY',
                company_address = 'Main Branch: S.No. 365, Opp. to Atma Malik Hospital, Nagar Manmad Road, Kokamthan.',
                company_branch_address = 'Branch: Opp. Rajpal, Nagar-Manmad Road, Tal. Kopargaon, Dist. Ahilyanagar.',
                company_phone = '9130119191, 9270047343',
                company_gstin = '27AFZFS1183A1ZP',
                company_state = 'Maharashtra',
                company_state_code = '27',
                brand_name = 'SK MOBILITY'
             WHERE company_gstin IS NULL OR company_gstin = '' OR company_gstin = '27AABCS1234A1Z5' OR company_name LIKE 'SK Mobility%'"
        );
        echo "updated existing bill headers where needed\n";
        echo "Migration complete.\n";
    }

    if (isset($_GET['migrate_variant']) && $_GET['migrate_variant'] === '1') {
        echo "\n--- Variant battery type migration ---\n";
        $addCol = static function (PDO $db, string $table, string $column, string $definition): void {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            if ((int)$stmt->fetchColumn() > 0) {
                echo "skip {$table}.{$column}\n";
                return;
            }
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            echo "added {$table}.{$column}\n";
        };
        $addCol($db, 'vehicle_variants', 'battery_type', "ENUM('Lithium Ion','Lead Acid') NULL AFTER battery_capacity_kwh");
        $addCol($db, 'vehicle_variants', 'battery_spec', 'VARCHAR(100) NULL AFTER battery_type');
        echo "Migration complete.\n";
    }

    if (isset($_GET['migrate_expenses']) && $_GET['migrate_expenses'] === '1') {
        echo "\n--- Expense record type migration ---\n";
        $addCol = static function (PDO $db, string $table, string $column, string $definition): void {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            if ((int)$stmt->fetchColumn() > 0) {
                echo "skip {$table}.{$column}\n";
                return;
            }
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            echo "added {$table}.{$column}\n";
        };
        $addCol($db, 'expenses', 'record_type', "ENUM('asset','expenditure') NOT NULL DEFAULT 'expenditure' AFTER category_id");
        $addCol($db, 'expenses', 'name', "VARCHAR(150) NOT NULL DEFAULT '' AFTER record_type");
        $addCol($db, 'expenses', 'gst_applicable', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER amount');
        $addCol($db, 'expenses', 'cgst_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER gst_applicable');
        $addCol($db, 'expenses', 'sgst_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER cgst_amount');
        $addCol($db, 'expenses', 'total_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER sgst_amount');
        $db->exec('UPDATE expenses SET total_amount = amount WHERE total_amount = 0');

        $tableExists = $db->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expense_items'"
        )->fetchColumn();
        if ((int)$tableExists === 0) {
            $db->exec(
                "CREATE TABLE expense_items (
                  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  expense_id INT UNSIGNED NOT NULL,
                  name VARCHAR(150) NOT NULL,
                  amount DECIMAL(10,2) NOT NULL,
                  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                  FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE,
                  INDEX idx_expense_items_expense (expense_id)
                ) ENGINE=InnoDB"
            );
            echo "created expense_items\n";
        } else {
            echo "skip expense_items\n";
        }

        $db->exec(
            "INSERT INTO expense_items (expense_id, name, amount, sort_order)
             SELECT e.id,
                    CASE WHEN e.name IS NOT NULL AND e.name <> '' THEN e.name ELSE CONCAT('Item #', e.id) END,
                    e.amount,
                    0
             FROM expenses e
             LEFT JOIN expense_items ei ON ei.expense_id = e.id
             WHERE ei.id IS NULL"
        );
        echo "backfilled expense_items\n";
        echo "Migration complete.\n";
    }

    if (isset($_GET['migrate_partners']) && $_GET['migrate_partners'] === '1') {
        echo "\n--- Partner Aadhar / PAN migration ---\n";
        $addCol = static function (PDO $db, string $table, string $column, string $definition): void {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            if ((int)$stmt->fetchColumn() > 0) {
                echo "skip {$table}.{$column}\n";
                return;
            }
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            echo "added {$table}.{$column}\n";
        };
        $addCol($db, 'partners', 'aadhar_number', 'VARCHAR(20) NULL AFTER address');
        $addCol($db, 'partners', 'pan_number', 'VARCHAR(20) NULL AFTER aadhar_number');
        echo "Migration complete.\n";
    }

    if (isset($_GET['migrate_purchase_orders']) && $_GET['migrate_purchase_orders'] === '1') {
        echo "\n--- Purchase orders migration ---\n";
        $migration = file_get_contents(dirname(__DIR__) . '/database/migrations/009_purchase_orders.sql');
        if ($migration === false) {
            throw new RuntimeException('Migration file 009_purchase_orders.sql not found.');
        }
        foreach (array_filter(array_map('trim', explode(';', $migration))) as $stmt) {
            $stmt = trim(preg_replace('/^--.*$/m', '', $stmt) ?? '');
            if ($stmt === '') {
                continue;
            }
            $db->exec($stmt);
            if (preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/i', $stmt, $m)) {
                echo "table {$m[1]}\n";
            }
        }
        echo "Migration complete.\n";
    }

    if (isset($_GET['migrate_po_supplier']) && $_GET['migrate_po_supplier'] === '1') {
        echo "\n--- PO supplier company migration ---\n";
        $addCol = static function (PDO $db, string $table, string $column, string $definition): void {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            if ((int)$stmt->fetchColumn() > 0) {
                echo "skip {$table}.{$column}\n";
                return;
            }
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            echo "added {$table}.{$column}\n";
        };
        $addCol($db, 'purchase_orders', 'supplier_name', 'VARCHAR(200) NULL AFTER po_number');
        $db->exec(
            'UPDATE purchase_orders po
             LEFT JOIN partners p ON p.id = po.partner_id
             SET po.supplier_name = p.name
             WHERE po.partner_id IS NOT NULL AND (po.supplier_name IS NULL OR po.supplier_name = \'\')'
        );
        echo "backfilled supplier_name from partners\n";

        $fkStmt = $db->prepare(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_orders'
               AND COLUMN_NAME = 'partner_id' AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1"
        );
        $fkStmt->execute();
        $fkName = $fkStmt->fetchColumn();
        if ($fkName) {
            $db->exec('ALTER TABLE purchase_orders DROP FOREIGN KEY `' . str_replace('`', '', (string)$fkName) . '`');
            echo "dropped partner_id foreign key\n";
        }

        $colStmt = $db->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $colStmt->execute(['purchase_orders', 'partner_id']);
        if ((int)$colStmt->fetchColumn() > 0) {
            $db->exec('ALTER TABLE purchase_orders DROP COLUMN partner_id');
            echo "dropped purchase_orders.partner_id\n";
        }
        echo "Migration complete.\n";
    }

    if (isset($_GET['migrate_po_spare_items']) && $_GET['migrate_po_spare_items'] === '1') {
        echo "\n--- PO spare parts line items migration ---\n";
        $colExists = static function (PDO $db, string $table, string $column): bool {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        };
        if (!$colExists($db, 'purchase_order_items', 'item_type')) {
            $db->exec(
                "ALTER TABLE purchase_order_items
                 ADD COLUMN item_type ENUM('vehicle_variant','spare_part') NOT NULL DEFAULT 'vehicle_variant' AFTER purchase_order_id"
            );
            echo "added purchase_order_items.item_type\n";
        } else {
            echo "skip purchase_order_items.item_type\n";
        }
        if (!$colExists($db, 'purchase_order_items', 'spare_part_id')) {
            $db->exec('ALTER TABLE purchase_order_items ADD COLUMN spare_part_id INT UNSIGNED NULL AFTER variant_id');
            echo "added purchase_order_items.spare_part_id\n";
        } else {
            echo "skip purchase_order_items.spare_part_id\n";
        }
        $db->exec('ALTER TABLE purchase_order_items MODIFY vehicle_id INT UNSIGNED NULL');
        $db->exec('ALTER TABLE purchase_order_items MODIFY variant_id INT UNSIGNED NULL');
        echo "nullable vehicle_id / variant_id\n";

        $fkStmt = $db->prepare(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_order_items'
               AND COLUMN_NAME = 'spare_part_id' AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1"
        );
        $fkStmt->execute();
        if (!$fkStmt->fetchColumn()) {
            $db->exec(
                'ALTER TABLE purchase_order_items
                 ADD CONSTRAINT fk_po_items_spare_part FOREIGN KEY (spare_part_id) REFERENCES spare_parts(id) ON DELETE SET NULL'
            );
            echo "added fk_po_items_spare_part\n";
        } else {
            echo "skip fk_po_items_spare_part\n";
        }

        $db->exec('ALTER TABLE purchase_order_receipt_lines MODIFY warehouse_id INT UNSIGNED NULL');
        echo "nullable purchase_order_receipt_lines.warehouse_id\n";
        echo "Migration complete.\n";
    }

    if (isset($_GET['migrate_billing_location']) && $_GET['migrate_billing_location'] === '1') {
        echo "\n--- Billing location migration ---\n";
        $colExists = static function (PDO $db, string $table, string $column): bool {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        };
        if (!$colExists($db, 'orders', 'billing_location')) {
            $db->exec(
                "ALTER TABLE orders
                 ADD COLUMN billing_location ENUM('kokamthan','kopargaon') NOT NULL DEFAULT 'kokamthan' AFTER order_type"
            );
            echo "added orders.billing_location\n";
        } else {
            echo "skip orders.billing_location\n";
        }
        if (!$colExists($db, 'bills', 'billing_location')) {
            $db->exec(
                "ALTER TABLE bills
                 ADD COLUMN billing_location ENUM('kokamthan','kopargaon') NOT NULL DEFAULT 'kokamthan' AFTER bill_type"
            );
            echo "added bills.billing_location\n";
        } else {
            echo "skip bills.billing_location\n";
        }
        echo "Migration complete.\n";
    }

    if (isset($_GET['migrate_sell_order_spare_parts']) && $_GET['migrate_sell_order_spare_parts'] === '1') {
        echo "\n--- Sell order spare parts migration ---\n";
        $colExists = static function (PDO $db, string $table, string $column): bool {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        };
        if (!$colExists($db, 'orders', 'product_type')) {
            $db->exec(
                "ALTER TABLE orders
                 ADD COLUMN product_type ENUM('vehicle','spare_part') NOT NULL DEFAULT 'vehicle' AFTER order_type"
            );
            echo "added orders.product_type\n";
        } else {
            echo "skip orders.product_type\n";
        }
        if (!$colExists($db, 'order_items', 'item_type')) {
            $db->exec(
                "ALTER TABLE order_items
                 ADD COLUMN item_type ENUM('vehicle_variant','spare_part') NOT NULL DEFAULT 'vehicle_variant' AFTER order_id"
            );
            echo "added order_items.item_type\n";
        } else {
            echo "skip order_items.item_type\n";
        }
        if (!$colExists($db, 'order_items', 'spare_part_id')) {
            $db->exec('ALTER TABLE order_items ADD COLUMN spare_part_id INT UNSIGNED NULL AFTER variant_id');
            echo "added order_items.spare_part_id\n";
        } else {
            echo "skip order_items.spare_part_id\n";
        }
        $db->exec('ALTER TABLE order_items MODIFY vehicle_id INT UNSIGNED NULL');
        $db->exec('ALTER TABLE order_items MODIFY variant_id INT UNSIGNED NULL');
        echo "nullable order_items vehicle_id / variant_id\n";
        $fkStmt = $db->query(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items'
               AND COLUMN_NAME = 'spare_part_id' AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1"
        );
        if (!$fkStmt->fetchColumn()) {
            $db->exec(
                'ALTER TABLE order_items
                 ADD CONSTRAINT fk_order_items_spare_part FOREIGN KEY (spare_part_id) REFERENCES spare_parts(id) ON DELETE SET NULL'
            );
            echo "added fk_order_items_spare_part\n";
        } else {
            echo "skip fk_order_items_spare_part\n";
        }
        $db->exec("ALTER TABLE bills MODIFY bill_type ENUM('vehicle','warranty','spare') NOT NULL DEFAULT 'vehicle'");
        echo "bills.bill_type includes spare\n";
        echo "Migration complete.\n";
    }

    if (isset($_GET['migrate_order_payment_partial']) && $_GET['migrate_order_payment_partial'] === '1') {
        echo "\n--- Order partial payment migration ---\n";
        $colExists = static function (PDO $db, string $table, string $column): bool {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        };
        $addPaymentCols = static function (PDO $db, string $table) use ($colExists): void {
            if (!$colExists($db, $table, 'payment_status')) {
                $db->exec(
                    "ALTER TABLE {$table}
                     ADD COLUMN payment_status ENUM('full','partial') NOT NULL DEFAULT 'full' AFTER payment_mode"
                );
                echo "added {$table}.payment_status\n";
            } else {
                echo "skip {$table}.payment_status\n";
            }
            if (!$colExists($db, $table, 'amount_paid')) {
                $db->exec(
                    "ALTER TABLE {$table}
                     ADD COLUMN amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER payment_status"
                );
                echo "added {$table}.amount_paid\n";
            } else {
                echo "skip {$table}.amount_paid\n";
            }
            if (!$colExists($db, $table, 'amount_due')) {
                $db->exec(
                    "ALTER TABLE {$table}
                     ADD COLUMN amount_due DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER amount_paid"
                );
                echo "added {$table}.amount_due\n";
            } else {
                echo "skip {$table}.amount_due\n";
            }
            $db->exec(
                "UPDATE {$table}
                 SET payment_status = 'full',
                     amount_paid = total_amount,
                     amount_due = 0
                 WHERE payment_status = 'full' AND amount_paid = 0 AND total_amount > 0"
            );
            echo "backfilled {$table} full-payment amounts\n";
        };
        $addPaymentCols($db, 'orders');
        $addPaymentCols($db, 'bills');
        echo "Migration complete.\n";
    }

    if (isset($_GET['migrate_sell_order_gst']) && $_GET['migrate_sell_order_gst'] === '1') {
        echo "\n--- Sell order GST rates migration ---\n";
        $colExists = static function (PDO $db, string $table, string $column): bool {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        };
        if (!$colExists($db, 'orders', 'cgst_rate')) {
            $db->exec(
                'ALTER TABLE orders
                 ADD COLUMN cgst_rate DECIMAL(5,2) NOT NULL DEFAULT 14 AFTER tax_amount,
                 ADD COLUMN sgst_rate DECIMAL(5,2) NOT NULL DEFAULT 14 AFTER cgst_rate,
                 ADD COLUMN tax_rate DECIMAL(5,2) NOT NULL DEFAULT 28 AFTER sgst_rate'
            );
            echo "added orders.cgst_rate, sgst_rate, tax_rate\n";
            $db->exec(
                "UPDATE orders o
                 LEFT JOIN bills b ON b.order_id = o.id
                 SET o.cgst_rate = COALESCE(b.cgst_rate, IF(o.product_type = 'spare_part', 9, 14)),
                     o.sgst_rate = COALESCE(b.sgst_rate, IF(o.product_type = 'spare_part', 9, 14)),
                     o.tax_rate = COALESCE(b.tax_rate, IF(o.product_type = 'spare_part', 18, 28))
                 WHERE o.cgst_rate = 14 OR o.tax_rate = 28"
            );
            echo "backfilled GST rates from bills / product type\n";
        } else {
            echo "skip orders GST rate columns\n";
        }
        echo "Migration complete.\n";
    }

    if (isset($_GET['migrate_po_product_type']) && $_GET['migrate_po_product_type'] === '1') {
        echo "\n--- Purchase order product type migration ---\n";
        $colExists = static function (PDO $db, string $table, string $column): bool {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        };
        if (!$colExists($db, 'purchase_orders', 'product_type')) {
            $db->exec(
                "ALTER TABLE purchase_orders
                 ADD COLUMN product_type ENUM('vehicle','spare_part') NOT NULL DEFAULT 'vehicle' AFTER po_number"
            );
            echo "added purchase_orders.product_type\n";
        } else {
            echo "skip purchase_orders.product_type (already exists)\n";
        }

        if ($colExists($db, 'purchase_order_items', 'item_type')) {
            $db->exec(
                "UPDATE purchase_orders po
                 SET product_type = 'spare_part'
                 WHERE EXISTS (
                     SELECT 1 FROM purchase_order_items poi
                     WHERE poi.purchase_order_id = po.id AND poi.item_type = 'spare_part'
                 )
                 AND NOT EXISTS (
                     SELECT 1 FROM purchase_order_items poi
                     WHERE poi.purchase_order_id = po.id AND poi.item_type = 'vehicle_variant'
                 )"
            );
            echo "backfilled product_type from item_type\n";
        } elseif ($colExists($db, 'purchase_order_items', 'spare_part_id')) {
            $db->exec(
                "UPDATE purchase_orders po
                 SET product_type = 'spare_part'
                 WHERE EXISTS (
                     SELECT 1 FROM purchase_order_items poi
                     WHERE poi.purchase_order_id = po.id AND poi.spare_part_id IS NOT NULL
                 )
                 AND NOT EXISTS (
                     SELECT 1 FROM purchase_order_items poi
                     WHERE poi.purchase_order_id = po.id AND poi.spare_part_id IS NULL
                 )"
            );
            echo "backfilled product_type from spare_part_id\n";
        } else {
            echo "skip backfill — all POs default to vehicle (run migrate_po_spare_items=1 first if needed)\n";
        }
        echo "Migration complete.\n";
    }

    if (isset($_GET['migrate_po_gst']) && $_GET['migrate_po_gst'] === '1') {
        echo "\n--- Purchase order GST rates migration ---\n";
        $colExists = static function (PDO $db, string $table, string $column): bool {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        };
        if (!$colExists($db, 'purchase_orders', 'cgst_rate')) {
            $db->exec(
                'ALTER TABLE purchase_orders
                 ADD COLUMN cgst_rate DECIMAL(5,2) NOT NULL DEFAULT 2.5 AFTER gst_amount,
                 ADD COLUMN sgst_rate DECIMAL(5,2) NOT NULL DEFAULT 2.5 AFTER cgst_rate,
                 ADD COLUMN tax_rate DECIMAL(5,2) NOT NULL DEFAULT 5 AFTER sgst_rate'
            );
            echo "added purchase_orders.cgst_rate, sgst_rate, tax_rate\n";
            if ($colExists($db, 'purchase_orders', 'product_type')) {
                $db->exec(
                    "UPDATE purchase_orders
                     SET cgst_rate = IF(product_type = 'spare_part', 9, 2.5),
                         sgst_rate = IF(product_type = 'spare_part', 9, 2.5),
                         tax_rate = IF(product_type = 'spare_part', 18, 5)"
                );
            } else {
                $db->exec(
                    'UPDATE purchase_orders SET cgst_rate = 2.5, sgst_rate = 2.5, tax_rate = 5'
                );
            }
            echo "backfilled PO GST rates\n";
        } else {
            echo "skip purchase_orders GST rate columns\n";
        }
        echo "Migration complete.\n";
    }

    if (isset($_GET['migrate_bank_transactions']) && $_GET['migrate_bank_transactions'] === '1') {
        echo "\n--- Bank transactions migration ---\n";
        $colExists = static function (PDO $db, string $table, string $column): bool {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        };
        $tableExists = static function (PDO $db, string $table): bool {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
            );
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        };

        if (!$tableExists($db, 'bank_transactions')) {
            $db->exec(
                "CREATE TABLE bank_transactions (
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
                ) ENGINE=InnoDB"
            );
            echo "created bank_transactions\n";
        } else {
            echo "skip bank_transactions table\n";
        }

        if (!$colExists($db, 'orders', 'bank_account_id')) {
            $db->exec(
                'ALTER TABLE orders
                 ADD COLUMN bank_account_id INT UNSIGNED NULL AFTER amount_due,
                 ADD COLUMN affect_bank_balance TINYINT(1) NOT NULL DEFAULT 0 AFTER bank_account_id'
            );
            $db->exec(
                'ALTER TABLE orders ADD CONSTRAINT fk_orders_bank_account
                 FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id)'
            );
            echo "added orders bank columns\n";
        } else {
            echo "skip orders bank columns\n";
        }

        if (!$colExists($db, 'purchase_orders', 'payment_status')) {
            $db->exec(
                "ALTER TABLE purchase_orders
                 ADD COLUMN payment_status ENUM('unpaid','full','partial') NOT NULL DEFAULT 'unpaid' AFTER total_amount,
                 ADD COLUMN amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER payment_status,
                 ADD COLUMN amount_due DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER amount_paid,
                 ADD COLUMN bank_account_id INT UNSIGNED NULL AFTER amount_due,
                 ADD COLUMN affect_bank_balance TINYINT(1) NOT NULL DEFAULT 0 AFTER bank_account_id"
            );
            $db->exec(
                'ALTER TABLE purchase_orders ADD CONSTRAINT fk_po_bank_account
                 FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id)'
            );
            $db->exec(
                'UPDATE purchase_orders SET amount_due = total_amount WHERE amount_due = 0 AND amount_paid = 0'
            );
            echo "added purchase_orders payment + bank columns\n";
        } else {
            echo "skip purchase_orders payment columns\n";
        }
        echo "Migration complete.\n";
    }

    if (isset($_GET['migrate_performance']) && $_GET['migrate_performance'] === '1') {
        echo "\n--- Performance indexes migration ---\n";
        $indexExists = static function (PDO $db, string $table, string $index): bool {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
            );
            $stmt->execute([$table, $index]);
            return (int)$stmt->fetchColumn() > 0;
        };
        $addIndex = static function (PDO $db, string $table, string $index, string $columns) use ($indexExists): void {
            if ($indexExists($db, $table, $index)) {
                echo "skip {$table}.{$index}\n";
                return;
            }
            try {
                $db->exec("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$columns})");
                echo "added {$table}.{$index}\n";
            } catch (Throwable $e) {
                echo "skip {$table}.{$index}: " . $e->getMessage() . "\n";
            }
        };

        $addIndex($db, 'notifications', 'idx_notifications_user_read', 'user_id, is_read');
        $addIndex($db, 'dealers', 'idx_dealers_user', 'user_id');
        $addIndex($db, 'dealers', 'idx_dealers_status_created', 'status, created_at');
        $addIndex($db, 'orders', 'idx_orders_status_created', 'status, created_at');
        $addIndex($db, 'orders', 'idx_orders_dealer_created', 'dealer_id, created_at');
        $addIndex($db, 'payments', 'idx_payments_status_date', 'status, payment_date');
        $addIndex($db, 'leads', 'idx_leads_status_created', 'status, created_at');
        $addIndex($db, 'leads', 'idx_leads_dealer_status', 'dealer_id, status');
        $addIndex($db, 'expenses', 'idx_expenses_date', 'expense_date');
        $addIndex($db, 'partner_transactions', 'idx_partner_tx_type_date', 'transaction_type, date');
        echo "Migration complete.\n";
    }
} catch (Throwable $e) {
    echo "DB error: " . $e->getMessage() . "\n";
}

echo "\nVisit /login with admin@skmobility.com / Admin@123\n";
echo "After migrate: delete public/install.php\n";
