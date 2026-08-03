<?php

declare(strict_types=1);

namespace Mindflex\Http;

use Mindflex\Support\Session;

/**
 * Pesan sekali tampil.
 * Versi lama mengirim pesan lewat query string index.php?msg=..., sehingga isi
 * pesan bisa disetir dari luar dan ikut tersimpan di riwayat browser.
 */
final class Flash
{
    private const TYPE_KEY = 'flash_type';
    private const MESSAGE_KEY = 'flash_message';

    public static function success(string $message): void
    {
        Session::put(self::TYPE_KEY, 'success');
        Session::put(self::MESSAGE_KEY, $message);
    }

    public static function error(string $message): void
    {
        Session::put(self::TYPE_KEY, 'error');
        Session::put(self::MESSAGE_KEY, $message);
    }

    /**
     * @return array{type: string, message: string}|null
     */
    public static function pull(): ?array
    {
        $message = Session::get(self::MESSAGE_KEY);

        if ($message === null) {
            return null;
        }

        $type = Session::get(self::TYPE_KEY, 'success') ?? 'success';

        Session::forget(self::TYPE_KEY);
        Session::forget(self::MESSAGE_KEY);

        return ['type' => $type, 'message' => $message];
    }
}
