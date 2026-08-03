<?php

declare(strict_types=1);

namespace Mindflex\Support;

/**
 * Pembaca baris hasil query. Mengubah nilai mixed dari PDO menjadi tipe pasti
 * di satu tempat, sehingga model tidak penuh dengan casting.
 */
final class Row
{
    /**
     * @param array<string, mixed> $row
     */
    public static function int(array $row, string $column, int $default = 0): int
    {
        $value = $row[$column] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function nullableInt(array $row, string $column): ?int
    {
        $value = $row[$column] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function float(array $row, string $column, float $default = 0.0): float
    {
        $value = $row[$column] ?? null;

        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function string(array $row, string $column, string $default = ''): string
    {
        $value = $row[$column] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function nullableString(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * Pecah hasil GROUP_CONCAT menjadi daftar nilai.
     *
     * @param array<string, mixed> $row
     * @param non-empty-string $separator
     * @return list<string>
     */
    public static function concatenatedList(array $row, string $column, string $separator = '|'): array
    {
        $value = self::nullableString($row, $column);

        if ($value === null || $value === '') {
            return [];
        }

        $items = array_filter(array_map('trim', explode($separator, $value)), static fn (string $item): bool => $item !== '');

        return array_values(array_unique($items));
    }
}
