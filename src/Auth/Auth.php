<?php
declare(strict_types=1);

namespace Wiki\Auth;

use Wiki\Models\User;

class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('wiki_sid');
        session_start();
    }

    public static function user(): ?array
    {
        static $cached = false;
        static $user = null;
        if ($cached) return $user;
        $cached = true;
        if (empty($_SESSION['uid'])) return $user = null;
        $u = User::find((int)$_SESSION['uid']);
        if (!$u || $u['status'] !== 'active') {
            unset($_SESSION['uid']);
            return $user = null;
        }
        return $user = $u;
    }

    public static function check(): bool { return self::user() !== null; }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u && $u['role'] === 'admin';
    }

    public static function isEditor(): bool
    {
        $u = self::user();
        return $u && in_array($u['role'], ['editor', 'admin'], true);
    }

    public static function login(string $username, string $password): bool
    {
        $u = User::findByUsername($username) ?? User::findByEmail($username);
        if (!$u || $u['status'] !== 'active') return false;
        if (!password_verify($password, $u['password_hash'])) return false;
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function csrf(): string
    {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
        return $_SESSION['csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }
}