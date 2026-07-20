<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use PDO;

class NotificationService
{
    private const UNREAD_CACHE_TTL = 45;

    public static function notifyUser(int $userId, string $title, string $message, ?string $type = null, ?string $entityType = null, ?int $entityId = null): void
    {
        $db = Database::connection();
        $db->prepare(
            'INSERT INTO notifications (user_id, title, message, type, entity_type, entity_id) VALUES (?,?,?,?,?,?)'
        )->execute([$userId, $title, $message, $type, $entityType, $entityId]);
        if (Auth::id() === $userId) {
            self::clearUnreadCache();
        }
    }

    public static function notifyRole(string $roleSlug, string $title, string $message, ?string $type = null, ?string $entityType = null, ?int $entityId = null): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = ? AND u.is_active = 1'
        );
        $stmt->execute([$roleSlug]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            self::notifyUser((int)$uid, $title, $message, $type, $entityType, $entityId);
        }
    }

    public static function unreadCount(?int $userId = null, bool $fresh = false): int
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) {
            return 0;
        }

        if (
            !$fresh
            && isset($_SESSION['unread_notifications'], $_SESSION['unread_notifications_at'])
            && (time() - (int)$_SESSION['unread_notifications_at']) < self::UNREAD_CACHE_TTL
        ) {
            return (int)$_SESSION['unread_notifications'];
        }

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0'
        );
        $stmt->execute([$userId]);
        $count = (int)$stmt->fetchColumn();
        $_SESSION['unread_notifications'] = $count;
        $_SESSION['unread_notifications_at'] = time();
        return $count;
    }

    public static function clearUnreadCache(): void
    {
        unset($_SESSION['unread_notifications'], $_SESSION['unread_notifications_at']);
    }
}
