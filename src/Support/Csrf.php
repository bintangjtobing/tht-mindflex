<?php

declare(strict_types=1);

namespace Mindflex\Support;

/**
 * Token CSRF per session.
 * Dashboard lama menghapus assignment lewat tautan GET, sehingga satu klik dari halaman
 * asing sudah cukup untuk menghapus data.
 */
final class Csrf
{
    public const FIELD_NAME = '_token';

    private const SESSION_KEY = 'csrf_token';

    public static function token(): string
    {
        $existingToken = Session::get(self::SESSION_KEY);

        if ($existingToken !== null && $existingToken !== '') {
            return $existingToken;
        }

        $newToken = bin2hex(random_bytes(32));
        Session::put(self::SESSION_KEY, $newToken);

        return $newToken;
    }

    public static function field(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::FIELD_NAME,
            e(self::token())
        );
    }

    public static function isValid(?string $submittedToken): bool
    {
        $expectedToken = Session::get(self::SESSION_KEY);

        if ($expectedToken === null || $submittedToken === null || $submittedToken === '') {
            return false;
        }

        return hash_equals($expectedToken, $submittedToken);
    }
}
