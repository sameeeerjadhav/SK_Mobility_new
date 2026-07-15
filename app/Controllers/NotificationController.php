<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\NotificationService;

class NotificationController extends Controller
{
    public function index(): void
    {
        require_auth();
        $stmt = $this->db()->prepare(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100'
        );
        $stmt->execute([Auth::id()]);
        $this->view('notifications/index', [
            'title' => 'Notifications',
            'notifications' => $stmt->fetchAll(),
        ]);
    }

    public function unreadCount(): void
    {
        require_auth();
        $this->json(['count' => NotificationService::unreadCount()]);
    }

    public function markRead(string $id): void
    {
        require_auth();
        $this->validateCsrf();
        $this->db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
            ->execute([(int)$id, Auth::id()]);
        flash('success', 'Marked as read.');
        $this->redirect('/notifications');
    }

    public function markAllRead(): void
    {
        require_auth();
        $this->validateCsrf();
        $this->db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')
            ->execute([Auth::id()]);
        flash('success', 'All notifications marked as read.');
        $this->redirect('/notifications');
    }
}
