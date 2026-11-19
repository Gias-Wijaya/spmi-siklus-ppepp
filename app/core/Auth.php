<?php
declare(strict_types=1);

/** Autentikasi & sesi pengguna (RBAC: super_admin = LPM, anggota = Waket I–III). */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function attempt(string $email, string $password): bool
    {
        $user = Database::fetch(
            'SELECT u.*, un.name AS unit_name
               FROM users u
               LEFT JOIN units un ON un.id = u.unit_id
              WHERE u.email = ?
              LIMIT 1',
            [trim($email)]
        );

        if (!$user || (int)$user['is_active'] !== 1) {
            return false;
        }
        if (!password_verify($password, (string)$user['password'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        Database::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [(int)$user['id']]);

        self::$loaded = false;
        self::$user   = null;
        return true;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;
        return $id === null ? null : (int)$id;
    }

    /** Data pengguna aktif yang sedang login (di-cache per request). */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;

        $id = self::id();
        if ($id === null) {
            return null;
        }

        self::$user = Database::fetch(
            'SELECT u.*, un.name AS unit_name
               FROM users u
               LEFT JOIN units un ON un.id = u.unit_id
              WHERE u.id = ? AND u.is_active = 1
              LIMIT 1',
            [$id]
        );

        if (self::$user === null) {
            unset($_SESSION['user_id']);
        }
        return self::$user;
    }

    public static function isSuperAdmin(): bool
    {
        return (string)(self::user()['role'] ?? '') === 'super_admin';
    }

    public static function unitId(): ?int
    {
        $unit = self::user()['unit_id'] ?? null;
        return $unit === null ? null : (int)$unit;
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
}
