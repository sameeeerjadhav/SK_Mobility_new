<?php

namespace App\Core;

class Upload
{
    public static function store(array $file, string $subdir, array $allowed = ['jpg','jpeg','png','gif','webp','pdf']): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            return null;
        }

        $dir = BASE_PATH . '/public/uploads/' . trim($subdir, '/');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $name = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return null;
        }

        return 'uploads/' . trim($subdir, '/') . '/' . $name;
    }

    public static function delete(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (str_contains($relativePath, '..')) {
            return;
        }

        $full = BASE_PATH . '/public/' . $relativePath;
        if (is_file($full)) {
            unlink($full);
        }
    }
}
