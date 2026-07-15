<?php

/**
 * Bootstrap with clearer deploy errors (Hostinger 500 diagnostics).
 */
define('BASE_PATH', dirname(__DIR__));

$errorHandler = static function (string $message): void {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>SK Mobility — Setup Error</title>';
    echo '<style>body{font-family:system-ui;padding:2rem;background:#f0faf8;color:#0f172a}';
    echo '.box{max-width:640px;margin:auto;background:#fff;border:1px solid #f1f5f9;border-radius:16px;padding:1.5rem}</style></head><body>';
    echo '<div class="box"><h1>Setup problem</h1><p>' . htmlspecialchars($message) . '</p>';
    echo '<p>Check: <code>.env</code> next to <code>app/</code>, MySQL import, document root = <code>public</code>.</p></div></body></html>';
    exit;
};

$helpers = BASE_PATH . '/app/Core/helpers.php';
if (!is_file($helpers)) {
    $errorHandler('App files missing. Upload the full project so app/ sits beside public/.');
}

require $helpers;

$envPath = BASE_PATH . '/.env';
if (!is_file($envPath)) {
    $errorHandler('Missing .env file at project root (same folder as app/ and public/). Copy .env.example to .env and set DB credentials.');
}

loadEnv($envPath);

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = BASE_PATH . '/app/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

if (session_status() === PHP_SESSION_NONE) {
    session_name(env('SESSION_NAME', 'sk_mobility_session'));
    session_start();
}

date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Kolkata'));

if (env('APP_DEBUG') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
