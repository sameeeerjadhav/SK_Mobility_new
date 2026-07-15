<?php

/**
 * Load .env file into $_ENV (preferred) and putenv.
 * Values may be quoted; $ in passwords is preserved.
 */
function loadEnv(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$key] = $value;
        // Avoid shell-style $ expansion issues: set via putenv without interpolation
        putenv($key . '=' . $value);
    }
}

function env(string $key, mixed $default = null): mixed
{
    if (array_key_exists($key, $_ENV)) {
        $val = $_ENV[$key];
        return $val === '' ? $default : $val;
    }
    $val = getenv($key);
    if ($val === false || $val === '') {
        return $default;
    }
    return $val;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $msg;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function store_old(array $data): void
{
    $_SESSION['_old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money(float|string|null $amount): string
{
    $n = (float)$amount;
    $parts = explode('.', number_format(abs($n), 2, '.', ''));
    $int = $parts[0];
    $dec = $parts[1] ?? '00';
    $last3 = substr($int, -3);
    $rest = substr($int, 0, -3);
    if ($rest !== '') {
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $int = $rest . ',' . $last3;
    } else {
        $int = $last3;
    }
    $sign = $n < 0 ? '-' : '';
    return $sign . '₹' . $int . '.' . $dec;
}

function india_date(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : '—';
}

function india_datetime(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('d/m/Y H:i', $ts) : '—';
}

function url(string $path = ''): string
{
    $base = rtrim((string)env('APP_URL', ''), '/');
    $appBase = rtrim((string)env('APP_BASE', ''), '/');
    $path = ltrim($path, '/');
    return $base . $appBase . ($path !== '' ? '/' . $path : '');
}

function asset(string $path): string
{
    return url(ltrim($path, '/'));
}

function status_chip(string $status): string
{
    $map = [
        'pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger',
        'suspended' => 'danger', 'processing' => 'info', 'shipped' => 'info',
        'delivered' => 'success', 'cancelled' => 'danger', 'completed' => 'success',
        'failed' => 'danger', 'refunded' => 'warning', 'active' => 'success',
        'inactive' => 'secondary', 'new' => 'info', 'contacted' => 'warning',
        'qualified' => 'primary', 'converted' => 'success', 'lost' => 'danger',
        'in_progress' => 'info', 'open' => 'warning',
    ];
    $color = $map[$status] ?? 'secondary';
    $label = ucfirst(str_replace('_', ' ', $status));
    return '<span class="chip chip-' . $color . '">' . e($label) . '</span>';
}

function csrf_field(): string
{
    return \App\Core\Csrf::field();
}

function can(string $permission): bool
{
    return \App\Core\Auth::can($permission);
}

function user(): ?array
{
    return \App\Core\Auth::user();
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'item';
}

function require_auth(): void
{
    if (!\App\Core\Auth::check()) {
        flash('error', 'Please log in to continue.');
        header('Location: ' . url('login'));
        exit;
    }
}

function require_permission(string $permission): void
{
    require_auth();
    if (!\App\Core\Auth::can($permission)) {
        http_response_code(403);
        \App\Core\View::render('errors/403', ['title' => 'Forbidden'], 'guest');
        exit;
    }
}

function require_role(string ...$roles): void
{
    require_auth();
    if (!in_array(\App\Core\Auth::role(), $roles, true)) {
        http_response_code(403);
        \App\Core\View::render('errors/403', ['title' => 'Forbidden'], 'guest');
        exit;
    }
}

function setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        try {
            $rows = \App\Core\Database::connection()
                ->query('SELECT setting_key, setting_value FROM system_settings')
                ->fetchAll(\PDO::FETCH_KEY_PAIR);
            $cache = $rows ?: [];
        } catch (\Throwable $e) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

function mom_trend(float $current, float $previous): float
{
    if ($previous == 0.0) {
        return $current > 0 ? 100.0 : 0.0;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

function next_code(string $prefix, string $table, string $column): string
{
    $db = \App\Core\Database::connection();
    $date = date('Ymd');
    $like = $prefix . '-' . $date . '-%';
    $stmt = $db->prepare("SELECT {$column} FROM {$table} WHERE {$column} LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$like]);
    $last = $stmt->fetchColumn();
    $seq = 1;
    if ($last && preg_match('/-(\d+)$/', $last, $m)) {
        $seq = (int)$m[1] + 1;
    }
    return sprintf('%s-%s-%04d', $prefix, $date, $seq);
}
