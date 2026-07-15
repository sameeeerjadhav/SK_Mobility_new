<?php

namespace App\Core;

use PDO;

class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT u.*, r.slug AS role_slug, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !(int)$user['is_active']) {
            return false;
        }

        // Support both $2y$ and $2b$ bcrypt hashes
        $hash = $user['password_hash'];
        if (str_starts_with($hash, '$2b$')) {
            $hash = '$2y$' . substr($hash, 4);
        }

        if (!password_verify($password, $hash) && !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        $_SESSION['permissions'] = self::loadPermissions((int)$user['role_id']);

        $db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int)$user['id']]);
        Audit::log('login', 'auth', 'users', (int)$user['id']);

        return true;
    }

    public static function loadPermissions(int $roleId): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT p.slug FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = ?'
        );
        $stmt->execute([$roleId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function role(): ?string
    {
        return $_SESSION['user']['role_slug'] ?? null;
    }

    public static function can(string $permission): bool
    {
        if (self::role() === 'super_admin') {
            return true;
        }
        $perms = $_SESSION['permissions'] ?? [];
        return in_array($permission, $perms, true);
    }

    public static function canAny(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if (self::can($p)) {
                return true;
            }
        }
        return false;
    }

    public static function logout(): void
    {
        if (self::check()) {
            Audit::log('logout', 'auth', 'users', self::id());
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function dealerId(): ?int
    {
        if (self::role() !== 'dealer') {
            return null;
        }
        $db = Database::connection();
        $stmt = $db->prepare('SELECT id FROM dealers WHERE user_id = ? LIMIT 1');
        $stmt->execute([self::id()]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }
}
