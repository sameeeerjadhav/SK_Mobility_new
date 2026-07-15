<?php

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/helpers.php';
loadEnv(BASE_PATH . '/.env');

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
