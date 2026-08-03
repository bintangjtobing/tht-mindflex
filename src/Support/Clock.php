<?php

declare(strict_types=1);

namespace Mindflex\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Semua timestamp disimpan dalam UTC.
 * Kode lama memakai date() yang mengikuti zona waktu server, jadi urutan data bisa kacau
 * begitu aplikasi pindah region.
 */
final class Clock
{
    private static ?string $frozenTimestamp = null;

    public static function nowUtc(): string
    {
        if (self::$frozenTimestamp !== null) {
            return self::$frozenTimestamp;
        }

        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    public static function freeze(string $timestamp): void
    {
        self::$frozenTimestamp = $timestamp;
    }

    public static function unfreeze(): void
    {
        self::$frozenTimestamp = null;
    }

    /**
     * Ubah timestamp UTC menjadi teks yang enak dibaca di dashboard.
     */
    public static function forDisplay(?string $utcTimestamp, string $timezone = 'UTC'): string
    {
        if ($utcTimestamp === null || $utcTimestamp === '') {
            return '-';
        }

        $parsed = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $utcTimestamp,
            new DateTimeZone('UTC')
        );

        if ($parsed === false) {
            return $utcTimestamp;
        }

        return $parsed->setTimezone(new DateTimeZone($timezone))->format('d M Y, H:i');
    }
}
