<?php

namespace App\Core;

use App\Core\Database;
use PDO;

class Controller
{
    protected function view(string $view, array $data = [], string $layout = 'app'): void
    {
        View::render($view, $data, $layout);
    }

    protected function redirect(string $path): void
    {
        $base = rtrim(env('APP_URL', ''), '/');
        $appBase = rtrim(env('APP_BASE', ''), '/');
        header('Location: ' . $base . $appBase . $path);
        exit;
    }

    protected function json(mixed $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function validateCsrf(): void
    {
        $token = $_POST['_csrf'] ?? '';
        if (!Csrf::verify($token)) {
            flash('error', 'Invalid security token. Please try again.');
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/dashboard');
        }
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        if (is_array($value)) {
            return $value;
        }
        if ($value === null) {
            return $default ?? '';
        }

        return trim((string)$value);
    }

    protected function db(): PDO
    {
        return Database::connection();
    }
}
