<?php

declare(strict_types=1);

namespace Mindflex\Database;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Memuat data demo dari file SQL.
 * Data awal dulu hanya hidup di dalam mindflex.db, jadi tidak bisa direview dan
 * tidak bisa dibangun ulang. Sekarang datanya berbentuk teks di dalam repo.
 */
final class Seeder
{
    public function __construct(
        private readonly PDO $connection,
        private readonly string $seedsPath,
    ) {
    }

    public function seed(): int
    {
        $paths = glob($this->seedsPath . '/*.sql');

        if ($paths === false || $paths === []) {
            throw new RuntimeException(sprintf('Tidak ada file seed di %s.', $this->seedsPath));
        }

        sort($paths);

        $this->connection->beginTransaction();

        try {
            foreach ($paths as $path) {
                $sql = file_get_contents($path);

                if ($sql === false) {
                    throw new RuntimeException(sprintf('Tidak bisa membaca seed %s.', basename($path)));
                }

                $this->connection->exec($sql);
            }

            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw new RuntimeException('Seed gagal: ' . $exception->getMessage(), 0, $exception);
        }

        return count($paths);
    }
}
