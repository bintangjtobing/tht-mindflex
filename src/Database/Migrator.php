<?php

declare(strict_types=1);

namespace Mindflex\Database;

use Mindflex\Support\Clock;
use Mindflex\Support\Database;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Menjalankan file .sql secara berurutan dan mencatat yang sudah dipakai.
 * Skema lama hanya ada sebagai file .db biner, jadi tidak ada yang bisa mereview
 * perubahan struktur. Sekarang setiap perubahan tampil sebagai diff.
 */
final class Migrator
{
    public function __construct(
        private readonly PDO $connection,
        private readonly string $migrationsPath,
    ) {
    }

    /**
     * @return list<string> Nama migration yang baru saja dijalankan.
     */
    public function migrate(): array
    {
        $this->createLedgerTable();

        $alreadyApplied = $this->appliedMigrations();
        $executedNow = [];

        foreach ($this->migrationFiles() as $migrationName => $filePath) {
            if (in_array($migrationName, $alreadyApplied, true)) {
                continue;
            }

            $this->runSingleMigration($migrationName, $filePath);
            $executedNow[] = $migrationName;
        }

        if ($executedNow !== []) {
            $this->assertForeignKeysAreValid();
        }

        return $executedNow;
    }

    /**
     * @return list<string>
     */
    public function appliedMigrations(): array
    {
        $this->createLedgerTable();

        return Database::fetchColumnValues(
            $this->connection,
            'SELECT migration FROM schema_migrations ORDER BY migration'
        );
    }

    /**
     * @return array<string, string> Nama migration dipetakan ke path file.
     */
    public function migrationFiles(): array
    {
        $paths = glob($this->migrationsPath . '/*.sql');

        if ($paths === false || $paths === []) {
            throw new RuntimeException(sprintf('Tidak ada migration di %s.', $this->migrationsPath));
        }

        sort($paths);

        $files = [];

        foreach ($paths as $path) {
            $files[basename($path, '.sql')] = $path;
        }

        return $files;
    }

    private function runSingleMigration(string $migrationName, string $filePath): void
    {
        $sql = file_get_contents($filePath);

        if ($sql === false) {
            throw new RuntimeException(sprintf('Tidak bisa membaca migration %s.', $migrationName));
        }

        // SQLite mengabaikan pragma ini di dalam transaksi, jadi dimatikan lebih dulu.
        // Beberapa migration membangun ulang tabel dan sementara memutus relasi.
        $this->connection->exec('PRAGMA foreign_keys = OFF');
        $this->connection->beginTransaction();

        try {
            $this->connection->exec($sql);

            $statement = $this->connection->prepare(
                'INSERT INTO schema_migrations (migration, applied_at) VALUES (:migration, :applied_at)'
            );
            $statement->execute([
                'migration' => $migrationName,
                'applied_at' => Clock::nowUtc(),
            ]);

            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw new RuntimeException(
                sprintf('Migration %s gagal: %s', $migrationName, $exception->getMessage()),
                0,
                $exception
            );
        } finally {
            $this->connection->exec('PRAGMA foreign_keys = ON');
        }
    }

    private function createLedgerTable(): void
    {
        $this->connection->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                migration TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL
            )'
        );
    }

    private function assertForeignKeysAreValid(): void
    {
        $violations = Database::fetchAll($this->connection, 'PRAGMA foreign_key_check');

        if ($violations !== []) {
            throw new RuntimeException(
                sprintf('Migration menyisakan %d baris yang melanggar foreign key.', count($violations))
            );
        }
    }
}
