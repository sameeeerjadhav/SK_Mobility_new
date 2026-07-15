<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;

class ProfileController extends Controller
{
    public function index(): void
    {
        $dealer = null;
        if (Auth::role() === 'dealer') {
            $stmt = $this->db()->prepare('SELECT * FROM dealers WHERE user_id = ? LIMIT 1');
            $stmt->execute([Auth::id()]);
            $dealer = $stmt->fetch() ?: null;
        }
        $this->view('profile/index', [
            'title' => 'My Profile',
            'dealer' => $dealer,
        ]);
    }

    public function update(): void
    {
        $this->validateCsrf();
        $first = $this->input('first_name');
        $last = $this->input('last_name');
        $phone = $this->input('phone');

        $this->db()->prepare(
            'UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?'
        )->execute([$first, $last, $phone, Auth::id()]);

        $_SESSION['user']['first_name'] = $first;
        $_SESSION['user']['last_name'] = $last;
        $_SESSION['user']['phone'] = $phone;

        Audit::log('update', 'profile', 'users', Auth::id(), null, compact('first', 'last', 'phone'));
        flash('success', 'Profile updated.');
        $this->redirect('/profile');
    }

    public function changePassword(): void
    {
        $this->validateCsrf();
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new !== $confirm || strlen($new) < 6) {
            flash('error', 'New passwords must match and be at least 6 characters.');
            $this->redirect('/profile');
        }

        $stmt = $this->db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([Auth::id()]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            flash('error', 'Current password is incorrect.');
            $this->redirect('/profile');
        }

        $this->db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_BCRYPT), Auth::id()]);

        Audit::log('update', 'profile', 'users', Auth::id(), null, ['password' => 'changed']);
        flash('success', 'Password changed successfully.');
        $this->redirect('/profile');
    }
}
