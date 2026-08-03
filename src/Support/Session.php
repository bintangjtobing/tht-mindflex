<?php

declare(strict_types=1);

namespace Mindflex\Support;

/**
 * Pembungkus tipis untuk $_SESSION supaya cookie selalu dipasang dengan flag aman.
 */
final class Session
{
    public static function start(string $name = 'mindflex_admin'): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (PHP_SAPI === 'cli') {
            return;
        }

        session_name($name);
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Strict',
            'secure' => self::isHttps(),
            'path' => '/',
        ]);
        session_start();
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_SESSION[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    public static function put(string $key, string $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        session_destroy();
    }

    private static function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';

        return is_string($https) && $https !== '' && strtolower($https) !== 'off';
    }
}
