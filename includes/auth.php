<?php
require_once __DIR__ . '/db.php';

class Auth {
    public static function login(string $username, string $password): bool {
        $user = DB::one(
            'SELECT * FROM users WHERE (username=? OR email=?) AND is_active=1',
            [$username, $username]
        );
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['logged_in'] = true;
        return true;
    }

    public static function logout(): void {
        $_SESSION = [];
        session_destroy();
    }

    public static function check(): bool {
        return !empty($_SESSION['logged_in']);
    }

    public static function role(): string {
        return $_SESSION['role'] ?? '';
    }

    public static function id(): int {
        return (int)($_SESSION['user_id'] ?? 0);
    }

    public static function fullName(): string {
        return $_SESSION['full_name'] ?? '';
    }

    public static function requireLogin(): void {
        if (!self::check()) {
            header('Location: ' . url('login.php'));
            exit;
        }
    }

    public static function requireAdmin(): void {
        self::requireLogin();
        if (self::role() !== 'admin') {
            http_response_code(403);
            include ROOT_PATH . '/public/errors/403.php';
            exit;
        }
    }

    public static function isAdmin(): bool {
        return self::role() === 'admin';
    }

    public static function isOfficer(): bool {
        return in_array(self::role(), ['admin', 'officer'], true);
    }
}
