<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;

class AdminController extends Controller
{
    public function index(): void
    {
        require_role('super_admin');
        $tab = $this->input('tab') ?: 'users';

        $users = $this->db()->query(
            'SELECT u.*, r.name AS role_name, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.created_at DESC'
        )->fetchAll();
        $roles = $this->db()->query('SELECT * FROM roles ORDER BY id')->fetchAll();
        $permissions = $this->db()->query('SELECT * FROM permissions ORDER BY module, name')->fetchAll();

        $rolePerms = [];
        foreach ($this->db()->query('SELECT role_id, permission_id FROM role_permissions')->fetchAll() as $rp) {
            $rolePerms[(int)$rp['role_id']][] = (int)$rp['permission_id'];
        }

        $module = $this->input('module');
        $action = $this->input('action');
        $where = ['1=1'];
        $params = [];
        if ($module !== '') {
            $where[] = 'a.module = ?';
            $params[] = $module;
        }
        if ($action !== '') {
            $where[] = 'a.action = ?';
            $params[] = $action;
        }
        $sqlWhere = implode(' AND ', $where);
        $logs = $this->db()->prepare(
            "SELECT a.*, u.first_name, u.last_name, u.email FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE {$sqlWhere} ORDER BY a.created_at DESC LIMIT 150"
        );
        $logs->execute($params);

        $settings = $this->db()->query('SELECT * FROM system_settings ORDER BY setting_key')->fetchAll();

        $this->view('admin/index', [
            'title' => 'Admin Panel',
            'tab' => $tab,
            'users' => $users,
            'roles' => $roles,
            'permissions' => $permissions,
            'rolePerms' => $rolePerms,
            'logs' => $logs->fetchAll(),
            'settings' => $settings,
            'filterModule' => $module,
            'filterAction' => $action,
        ]);
    }

    public function storeUser(): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $roleSlug = $this->input('role_slug') ?: 'dealer';
        $st = $this->db()->prepare('SELECT id FROM roles WHERE slug = ?');
        $st->execute([$roleSlug]);
        $roleId = (int)$st->fetchColumn();
        if (!$roleId) {
            flash('error', 'Invalid role.');
            $this->redirect('/admin?tab=users');
        }
        $pass = $this->input('password') ?: ('User@' . random_int(1000, 9999));
        $this->db()->prepare(
            'INSERT INTO users (role_id, email, password_hash, first_name, last_name, phone, is_active, is_verified)
             VALUES (?,?,?,?,?,?,1,1)'
        )->execute([
            $roleId,
            $this->input('email'),
            password_hash($pass, PASSWORD_BCRYPT),
            $this->input('first_name'),
            $this->input('last_name'),
            $this->input('phone'),
        ]);
        Audit::log('create', 'admin', 'users', (int)$this->db()->lastInsertId());
        flash('success', "User created. Temp password: {$pass}");
        $this->redirect('/admin?tab=users');
    }

    public function updateUser(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $roleSlug = $this->input('role_slug');
        $st = $this->db()->prepare('SELECT id FROM roles WHERE slug = ?');
        $st->execute([$roleSlug]);
        $roleId = (int)$st->fetchColumn();
        $this->db()->prepare(
            'UPDATE users SET first_name=?, last_name=?, phone=?, role_id=?, is_active=? WHERE id=?'
        )->execute([
            $this->input('first_name'),
            $this->input('last_name'),
            $this->input('phone'),
            $roleId,
            (int)$this->input('is_active'),
            (int)$id,
        ]);
        Audit::log('update', 'admin', 'users', (int)$id);
        flash('success', 'User updated.');
        $this->redirect('/admin?tab=users');
    }

    public function toggleUser(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $this->db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([(int)$id]);
        Audit::log('update', 'admin', 'users', (int)$id);
        flash('success', 'User status toggled.');
        $this->redirect('/admin?tab=users');
    }

    public function updateRolePermissions(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $roleId = (int)$id;
        $perms = $_POST['permissions'] ?? [];
        if (!is_array($perms)) {
            $perms = [];
        }
        $this->db()->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$roleId]);
        $ins = $this->db()->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)');
        foreach ($perms as $pid) {
            $ins->execute([$roleId, (int)$pid]);
        }
        Audit::log('update', 'admin', 'roles', $roleId, null, ['permissions' => $perms]);
        flash('success', 'Role permissions updated.');
        $this->redirect('/admin?tab=roles');
    }

    public function updateSetting(string $key): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $this->db()->prepare(
            'INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        )->execute([$key, $this->input('setting_value')]);
        Audit::log('update', 'admin', 'system_settings', null, null, [$key => $this->input('setting_value')]);
        flash('success', 'Setting updated.');
        $this->redirect('/admin?tab=settings');
    }
}
