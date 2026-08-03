<?php

declare(strict_types=1);

namespace Mindflex\Support;

use Dotenv\Dotenv;
use RuntimeException;

/**
 * Sumber tunggal untuk konfigurasi aplikasi.
 * Nilai dibaca dari .env sekali saat boot, lalu dipakai lewat getter bertipe.
 */
final class Config
{
    private static ?self $instance = null;

    /**
     * @param array<string, string> $values
     */
    private function __construct(
        private readonly string $projectRoot,
        private readonly array $values,
    ) {
    }

    public static function load(?string $projectRoot = null): self
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }

        $projectRoot ??= base_path();

        if (is_file($projectRoot . '/.env')) {
            Dotenv::createImmutable($projectRoot)->safeLoad();
        }

        $rawValues = array_merge($_ENV, $_SERVER);
        $values = [];

        foreach ($rawValues as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $values[$key] = (string) $value;
            }
        }

        self::$instance = new self($projectRoot, $values);

        return self::$instance;
    }

    /**
     * Dipakai test untuk memasang konfigurasi buatan.
     *
     * @param array<string, string> $values
     */
    public static function fake(string $projectRoot, array $values): self
    {
        self::$instance = new self($projectRoot, $values);

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function projectRoot(): string
    {
        return $this->projectRoot;
    }

    public function string(string $key, ?string $default = null): string
    {
        $value = $this->values[$key] ?? $default;

        if ($value === null || $value === '') {
            throw new RuntimeException(
                sprintf('Konfigurasi "%s" belum diisi. Salin .env.example menjadi .env.', $key)
            );
        }

        return $value;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->values[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public function float(string $key, float $default): float
    {
        $value = $this->values[$key] ?? null;

        return is_numeric($value) ? (float) $value : $default;
    }

    public function environment(): string
    {
        return $this->values['APP_ENV'] ?? 'production';
    }

    public function isDebug(): bool
    {
        return $this->boolean('APP_DEBUG', false);
    }

    public function isTesting(): bool
    {
        return $this->environment() === 'testing';
    }

    public function currency(): string
    {
        return $this->values['APP_CURRENCY'] ?? 'USD';
    }

    /**
     * Path database. Nilai ":memory:" dipakai suite test.
     */
    public function databasePath(): string
    {
        $path = $this->string('DB_PATH', 'storage/mindflex.db');

        if ($path === ':memory:' || str_starts_with($path, '/')) {
            return $path;
        }

        return $this->projectRoot . DIRECTORY_SEPARATOR . $path;
    }

    public function adminUsername(): string
    {
        return $this->string('ADMIN_USERNAME', 'admin');
    }

    public function adminPasswordHash(): string
    {
        return $this->string('ADMIN_PASSWORD_HASH');
    }

    public function apiKey(): string
    {
        return $this->string('API_KEY');
    }

    /**
     * Toleransi budget. 1.0 berarti biaya mingguan tidak boleh melewati budget student.
     */
    public function budgetTolerance(): float
    {
        return max(1.0, $this->float('MATCHING_BUDGET_TOLERANCE', 1.0));
    }
}
