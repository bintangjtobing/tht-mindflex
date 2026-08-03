<?php

declare(strict_types=1);

namespace Mindflex\Tests;

use Mindflex\Container;
use Mindflex\Database\Migrator;
use Mindflex\Support\Clock;
use Mindflex\Support\Config;
use Mindflex\Support\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Setiap test memakai database SQLite di memori yang dibangun dari migration asli.
 * Skema yang diuji selalu sama dengan skema yang dipakai produksi.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected Container $container;
    protected PDO $connection;

    protected function setUp(): void
    {
        parent::setUp();

        Config::reset();
        Database::reset();
        Clock::freeze('2026-06-01 09:00:00');

        $projectRoot = dirname(__DIR__);

        $config = Config::fake($projectRoot, [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'true',
            'APP_CURRENCY' => 'USD',
            'DB_PATH' => ':memory:',
            'ADMIN_USERNAME' => 'admin',
            'ADMIN_PASSWORD_HASH' => password_hash('secret-password', PASSWORD_BCRYPT),
            'API_KEY' => 'test-api-key',
            'MATCHING_BUDGET_TOLERANCE' => '1.0',
        ]);

        $this->container = Container::fromConfig($config);
        $this->connection = $this->container->connection();

        (new Migrator($this->connection, $projectRoot . '/database/migrations'))->migrate();
    }

    protected function tearDown(): void
    {
        Clock::unfreeze();
        Database::reset();
        Config::reset();

        parent::tearDown();
    }

    /**
     * @param list<string> $subjectNames
     */
    protected function makeTutor(
        string $name,
        float $hourlyRate,
        array $subjectNames,
        int $maxWeeklyHours = 40,
        string $status = 'active',
        float $rating = 0.0,
        int $reviewCount = 0,
    ): int {
        $tutorId = $this->container->tutorService()->register(
            $name,
            strtolower(str_replace(' ', '.', $name)) . '@example.com',
            (int) round($hourlyRate * 100),
            $subjectNames,
            $maxWeeklyHours,
        );

        $statement = $this->connection->prepare(
            'UPDATE tutors SET status = :status, rating = :rating, review_count = :review_count WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'rating' => $rating,
            'review_count' => $reviewCount,
            'id' => $tutorId,
        ]);

        return $tutorId;
    }

    protected function makeStudent(string $name, float $weeklyBudget, string $gradeLevel = '10th Grade'): int
    {
        return $this->container->studentRepository()->create($name, $gradeLevel, (int) round($weeklyBudget * 100));
    }

    protected function subjectId(string $name): int
    {
        return $this->container->subjectRepository()->firstOrCreate($name);
    }
}
