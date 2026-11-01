<?php
/**
 * Global configuration - edit the DB_* values if your WAMP MySQL differs.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Colombo');

define('DB_HOST', 'localhost');
define('DB_NAME', 'inventory_pos');
define('DB_USER', 'root');
define('DB_PASS', '');            // WAMP default root password is empty
define('DB_TZ',   '+05:30');      // keeps MySQL NOW()/CURDATE() in sync with PHP

define('BASE_URL', '/inventory_pos');
define('UPLOAD_DIR', dirname(__DIR__) . '/assets/uploads/');
define('UPLOAD_URL', BASE_URL . '/assets/uploads/');
define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024);

define('CURRENCY', 'Rs.');
define('STORE_NAME', 'FreshMart Supermarket');
define('STORE_ADDRESS', '123 Main Street, Colombo');
define('STORE_PHONE', 'Tel: 011 234 5678');

spl_autoload_register(function (string $class): void {
    $file = dirname(__DIR__) . '/classes/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/database.php';

/* ---------- helpers ---------- */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function money($v): string
{
    return CURRENCY . ' ' . number_format((float)$v, 2);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): bool
{
    return hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '');
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function get_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
