<?php

declare(strict_types=1);

namespace Mindflex\Repository;

use Mindflex\Model\Student;
use Mindflex\Support\Clock;
use Mindflex\Support\Database;
use PDO;

/**
 * Akses tabel students.
 * Setiap baris membawa total komitmen mingguan yang masih berjalan, sehingga
 * pengecekan budget tidak perlu query tambahan.
 */
final class StudentRepository
{
    private const BASE_SELECT = <<<'SQL'
        SELECT
            students.id,
            students.name,
            students.grade_level,
            students.weekly_budget_cents,
            COALESCE(committed.weekly_cost_cents, 0) AS committed_weekly_cost_cents
        FROM students
        LEFT JOIN (
            SELECT student_id, SUM(weekly_hours * hourly_rate_cents) AS weekly_cost_cents
            FROM assignments
            WHERE status IN ('pending', 'active')
            GROUP BY student_id
        ) AS committed ON committed.student_id = students.id
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    /**
     * @return list<Student>
     */
    public function all(): array
    {
        $rows = Database::fetchAll($this->connection, self::BASE_SELECT . ' ORDER BY students.name');

        return array_map(Student::fromRow(...), $rows);
    }

    public function find(int $studentId): ?Student
    {
        $statement = $this->connection->prepare(self::BASE_SELECT . ' WHERE students.id = :id');
        $statement->execute(['id' => $studentId]);

        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch();

        return $row === false ? null : Student::fromRow($row);
    }

    /**
     * Student yang total komitmen mingguannya sudah melewati budget.
     *
     * @return list<Student>
     */
    public function overBudget(): array
    {
        $rows = Database::fetchAll(
            $this->connection,
            self::BASE_SELECT . ' WHERE committed.weekly_cost_cents > students.weekly_budget_cents ORDER BY students.name'
        );

        return array_map(Student::fromRow(...), $rows);
    }

    public function create(string $name, string $gradeLevel, int $weeklyBudgetCents): int
    {
        $timestamp = Clock::nowUtc();

        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO students (name, grade_level, weekly_budget_cents, created_at, updated_at)
            VALUES (:name, :grade_level, :weekly_budget_cents, :created_at, :updated_at)
            SQL
        );
        $statement->execute([
            'name' => $name,
            'grade_level' => $gradeLevel,
            'weekly_budget_cents' => $weeklyBudgetCents,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
