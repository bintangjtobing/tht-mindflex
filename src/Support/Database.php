<?php

declare(strict_types=1);

namespace Mindflex\Support;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Satu pintu untuk membuat koneksi PDO.
 * Foreign key SQLite mati secara default, jadi selalu dinyalakan di sini.
 */
final class Database
{
    private static ?PDO $sharedConnection = null;

    public static function connection(Config $config): PDO
    {
        if (self::$sharedConnection instanceof PDO) {
            return self::$sharedConnection;
        }

        self::$sharedConnection = self::connect($config->databasePath());

        return self::$sharedConnection;
    }

    public static function connect(string $databasePath): PDO
    {
        if ($databasePath !== ':memory:') {
            self::ensureDirectoryExists(dirname($databasePath));
        }

        try {
            $connection = new PDO('sqlite:' . $databasePath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Gagal membuka database. Jalankan "composer fresh" untuk membangun ulang.',
                previous: $exception
            );
        }

        $connection->exec('PRAGMA foreign_keys = ON');
        $connection->exec('PRAGMA busy_timeout = 5000');

        if ($databasePath !== ':memory:') {
            $connection->exec('PRAGMA journal_mode = WAL');
        }

        return $connection;
    }

    public static function reset(): void
    {
        self::$sharedConnection = null;
    }

    /**
     * Jalankan query tanpa parameter lalu ambil semua baris.
     * PDO::query() bisa mengembalikan false, jadi pemeriksaannya dikumpulkan di sini
     * daripada diulang di setiap repository.
     *
     * @return list<array<string, mixed>>
     */
    public static function fetchAll(PDO $connection, string $sql): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = self::runQuery($connection, $sql)->fetchAll();

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function fetchFirst(PDO $connection, string $sql): ?array
    {
        $row = self::runQuery($connection, $sql)->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<string>
     */
    public static function fetchColumnValues(PDO $connection, string $sql): array
    {
        /** @var list<string> $values */
        $values = self::runQuery($connection, $sql)->fetchAll(PDO::FETCH_COLUMN);

        return $values;
    }

    private static function runQuery(PDO $connection, string $sql): PDOStatement
    {
        $statement = $connection->query($sql);

        if ($statement === false) {
            throw new RuntimeException('Query gagal dijalankan: ' . $sql);
        }

        return $statement;
    }

    private static function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Tidak bisa membuat folder database: %s', $directory));
        }
    }
}
