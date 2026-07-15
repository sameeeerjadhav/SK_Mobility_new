<?php
/**
 * One-time installer: create admin password hash / verify PHP environment.
 * Open once after deploy, then delete this file.
 */
require dirname(__DIR__) . '/app/Config/bootstrap.php';

header('Content-Type: text/plain');

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
} catch (Throwable $e) {
    echo "DB error: " . $e->getMessage() . "\n";
}

echo "\nVisit /login with admin@skmobility.com / Admin@123\n";
echo "Delete public/install.php after setup.\n";
