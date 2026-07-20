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
} catch (Throwable $e) {
    echo "DB error: " . $e->getMessage() . "\n";
}

echo "\nVisit /login with admin@skmobility.com / Admin@123\n";
echo "After migrate: delete public/install.php\n";
