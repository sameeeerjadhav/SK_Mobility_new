<?php

namespace App\Core;

class Audit
{
    public static function log(
        string $action,
        string $module,
        ?string $entityType = null,
        ?int $entityId = null,
        mixed $oldValues = null,
        mixed $newValues = null
    ): void {
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                'INSERT INTO audit_logs (user_id, action, module, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                Auth::id(),
                $action,
                $module,
                $entityType,
                $entityId,
                $oldValues !== null ? json_encode($oldValues) : null,
                $newValues !== null ? json_encode($newValues) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
        } catch (\Throwable $e) {
            // never break the app for audit failure
        }
    }
}
