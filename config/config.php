<?php
// Load .env file if it exists
function loadEnv(string $path): void {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!isset($_ENV[$key]) && !isset($_SERVER[$key])) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

loadEnv(__DIR__ . '/../.env');

function env(string $key, mixed $default = null): mixed {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// ── Database ──────────────────────────────────────────────────────────────────
$_db = [
    'host' => env('DB_HOST', 'localhost'),
    'port' => env('DB_PORT', '3306'),
    'name' => env('DB_NAME', 'docreg'),
    'user' => env('DB_USER', 'root'),
    'pass' => env('DB_PASS', ''),
];
define('DB_HOST', $_db['host']);
define('DB_PORT', $_db['port']);
define('DB_NAME', $_db['name']);
define('DB_USER', $_db['user']);
define('DB_PASS', $_db['pass']);
unset($_db);

// ── Application ───────────────────────────────────────────────────────────────
define('APP_NAME',      env('APP_NAME', 'Система за Входиране на Документи'));
define('APP_BASE_URL',  rtrim(env('APP_BASE_URL', 'http://localhost'), '/'));
define('APP_BASE_PATH', '/' . trim(env('APP_BASE_PATH', '/'), '/'));

// ── Paths ─────────────────────────────────────────────────────────────────────
define('ROOT_PATH',    dirname(__DIR__));
define('UPLOAD_PATH',  ROOT_PATH . '/' . env('UPLOAD_PATH', 'uploads/docs'));
define('QR_PATH',      ROOT_PATH . '/' . env('QR_PATH', 'uploads/qr'));
define('UPLOAD_MAX',   (int) env('UPLOAD_MAX_SIZE', 52428800));

// ── Session ───────────────────────────────────────────────────────────────────
define('SESSION_LIFETIME', (int) env('SESSION_LIFETIME', 3600));

// ── Crypto ────────────────────────────────────────────────────────────────────
define('CRYPTO_CIPHER', env('CRYPTO_CIPHER', 'AES-256-CBC'));

// ── Incoming number prefix ────────────────────────────────────────────────────
define('DOC_PREFIX', 'ВХ');

// ── Error reporting ───────────────────────────────────────────────────────────
ini_set('display_errors', env('APP_DEBUG', '0') === '1' ? '1' : '0');
error_reporting(E_ALL);

// ── Session config ────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => APP_BASE_PATH ?: '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// Helper: build URL relative to app base
function url(string $path = ''): string {
    $base = rtrim(APP_BASE_URL . APP_BASE_PATH, '/');
    return $base . '/' . ltrim($path, '/');
}
