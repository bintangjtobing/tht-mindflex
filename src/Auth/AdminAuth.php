<?php

declare(strict_types=1);

namespace Mindflex\Auth;

use Mindflex\Support\Config;
use Mindflex\Support\Session;

/**
 * Login admin sederhana.
 *
 * Dashboard lama tidak punya pemeriksaan identitas sama sekali. Siapa pun yang
 * bisa membuka URL bisa menghapus assignment atau mengubah tarif tutor.
 * Kredensial tersimpan sebagai hash di .env, cocok untuk satu tim admin internal.
 * Jika jumlah admin bertambah, pindahkan ke tabel users.
 */
final class AdminAuth
{
    private const SESSION_KEY = 'admin_username';

    public function __construct(private readonly Config $config)
    {
    }

    public function attempt(string $username, string $password): bool
    {
        $usernameMatches = hash_equals($this->config->adminUsername(), $username);
        $passwordMatches = password_verify($password, $this->config->adminPasswordHash());

        // Kedua pemeriksaan tetap dijalankan agar waktu respons tidak membocorkan
        // apakah username benar.
        if (! $usernameMatches || ! $passwordMatches) {
            return false;
        }

        Session::regenerate();
        Session::put(self::SESSION_KEY, $username);

        return true;
    }

    public function check(): bool
    {
        return Session::get(self::SESSION_KEY) !== null;
    }

    public function username(): string
    {
        return Session::get(self::SESSION_KEY) ?? 'guest';
    }

    public function logout(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::destroy();
    }
}
