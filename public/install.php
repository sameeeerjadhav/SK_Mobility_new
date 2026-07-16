<?php
/**
 * One-time installer / migrator for Hostinger.
 * Open after deploy, then delete this file.
 *
 *  /install.php
 *  /install.php?reset_admin=1
 *  /install.php?migrate_invoice=1   ← SAI KUBER tax invoice columns + company settings
 *  /install.php?migrate_variant=1   ← vehicle variant battery type + capacity options
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
} catch (Throwable $e) {
    echo "DB error: " . $e->getMessage() . "\n";
}

echo "\nVisit /login with admin@skmobility.com / Admin@123\n";
echo "After migrate: delete public/install.php\n";
