<?php

declare(strict_types=1);

namespace Mindflex\Repository;

use Mindflex\Model\Assignment;
use Mindflex\Model\AssignmentStatus;
use Mindflex\Model\DashboardStats;
use Mindflex\Support\Clock;
use Mindflex\Support\Database;
use PDO;

/**
 * Akses tabel assignments.
 * Daftar dan statistik masing masing memakai satu query. Versi lama memanggil
 * database dua kali per baris untuk mengambil nama tutor dan nama student,
 * lalu sekali lagi per baris untuk menghitung pendapatan.
 */
final class AssignmentRepository
{
    private const BASE_SELECT = <<<'SQL'
        SELECT
            assignments.id,
            assignments.student_id,
            assignments.tutor_id,
            assignments.weekly_hours,
            assignments.hourly_rate_cents,
            assignments.status,
            assignments.created_at,
            students.name AS student_name,
            students.weekly_budget_cents AS student_weekly_budget_cents,
            tutors.name AS tutor_name,
            tutors.hourly_rate_cents AS current_tutor_rate_cents,
            subjects.name AS subject_name
        FROM assignments
        JOIN students ON students.id = assignments.student_id
        JOIN tutors ON tutors.id = assignments.tutor_id
        LEFT JOIN subjects ON subjects.id = assignments.subject_id
        SQL;

    private const STATUS_ORDER = <<<'SQL'
        ORDER BY
            CASE assignments.status
                WHEN 'active' THEN 0
                WHEN 'pending' THEN 1
                WHEN 'completed' THEN 2
                ELSE 3
            END,
            assignments.created_at DESC
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    /**
     * @return list<Assignment>
     */
    public function all(): array
    {
        $rows = Database::fetchAll($this->connection, self::BASE_SELECT . ' ' . self::STATUS_ORDER);

        return array_map(Assignment::fromRow(...), $rows);
    }

    /**
     * Match berjalan yang biayanya melewati budget student.
     *
     * @return list<Assignment>
     */
    public function openAndOverBudget(): array
    {
        $rows = Database::fetchAll(
            $this->connection,
            self::BASE_SELECT . <<<'SQL'
                 WHERE assignments.status IN ('pending', 'active')
                   AND (assignments.weekly_hours * assignments.hourly_rate_cents) > students.weekly_budget_cents
                 ORDER BY (assignments.weekly_hours * assignments.hourly_rate_cents) - students.weekly_budget_cents DESC
                SQL
        );

        return array_map(Assignment::fromRow(...), $rows);
    }

    public function find(int $assignmentId): ?Assignment
    {
        $statement = $this->connection->prepare(self::BASE_SELECT . ' WHERE assignments.id = :id');
        $statement->execute(['id' => $assignmentId]);

        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch();

        return $row === false ? null : Assignment::fromRow($row);
    }

    public function create(
        int $studentId,
        int $tutorId,
        ?int $subjectId,
        int $weeklyHours,
        int $agreedHourlyRateCents,
        AssignmentStatus $status = AssignmentStatus::Active,
    ): int {
        $timestamp = Clock::nowUtc();

        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO assignments (
                student_id, tutor_id, subject_id, weekly_hours,
                hourly_rate_cents, status, created_at, updated_at
            ) VALUES (
                :student_id, :tutor_id, :subject_id, :weekly_hours,
                :hourly_rate_cents, :status, :created_at, :updated_at
            )
            SQL
        );
        $statement->execute([
            'student_id' => $studentId,
            'tutor_id' => $tutorId,
            'subject_id' => $subjectId,
            'weekly_hours' => $weeklyHours,
            'hourly_rate_cents' => $agreedHourlyRateCents,
            'status' => $status->value,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function updateStatus(int $assignmentId, AssignmentStatus $status): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE assignments SET status = :status, updated_at = :updated_at WHERE id = :id'
        );
        $statement->execute([
            'status' => $status->value,
            'updated_at' => Clock::nowUtc(),
            'id' => $assignmentId,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * Jam mingguan tutor yang masih terpakai match berjalan.
     */
    public function openWeeklyHoursForTutor(int $tutorId): int
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT COALESCE(SUM(weekly_hours), 0)
            FROM assignments
            WHERE tutor_id = :tutor_id AND status IN ('pending', 'active')
            SQL
        );
        $statement->execute(['tutor_id' => $tutorId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Total biaya mingguan yang sudah dikomitmenkan seorang student.
     */
    public function openWeeklyCostCentsForStudent(int $studentId): int
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT COALESCE(SUM(weekly_hours * hourly_rate_cents), 0)
            FROM assignments
            WHERE student_id = :student_id AND status IN ('pending', 'active')
            SQL
        );
        $statement->execute(['student_id' => $studentId]);

        return (int) $statement->fetchColumn();
    }

    public function hasOpenAssignment(int $studentId, int $tutorId, ?int $subjectId): bool
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT 1
            FROM assignments
            WHERE student_id = :student_id
              AND tutor_id = :tutor_id
              AND COALESCE(subject_id, 0) = :subject_id
              AND status IN ('pending', 'active')
            LIMIT 1
            SQL
        );

        // COALESCE menghasilkan ekspresi tanpa afinitas kolom. Tanpa PARAM_INT,
        // SQLite membandingkan angka dengan teks dan hasilnya selalu tidak cocok.
        $statement->bindValue('student_id', $studentId, PDO::PARAM_INT);
        $statement->bindValue('tutor_id', $tutorId, PDO::PARAM_INT);
        $statement->bindValue('subject_id', $subjectId ?? 0, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchColumn() !== false;
    }

    /**
     * Pendapatan memakai tarif yang tersimpan di assignment, bukan tarif tutor hari ini.
     */
    public function stats(): DashboardStats
    {
        $row = Database::fetchFirst(
            $this->connection,
            <<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM tutors) AS total_tutors,
                (SELECT COUNT(*) FROM tutors WHERE status = 'active') AS active_tutors,
                (SELECT COUNT(*) FROM students) AS total_students,
                (SELECT COUNT(*) FROM assignments WHERE status = 'active') AS active_assignments,
                (
                    SELECT COALESCE(SUM(weekly_hours * hourly_rate_cents), 0)
                    FROM assignments
                    WHERE status = 'active'
                ) AS weekly_revenue_cents,
                (
                    SELECT COUNT(*)
                    FROM assignments
                    JOIN students ON students.id = assignments.student_id
                    WHERE assignments.status IN ('pending', 'active')
                      AND (assignments.weekly_hours * assignments.hourly_rate_cents) > students.weekly_budget_cents
                ) AS assignments_over_budget,
                (
                    SELECT COUNT(*)
                    FROM tutors
                    WHERE status = 'active'
                      AND max_weekly_hours <= (
                          SELECT COALESCE(SUM(weekly_hours), 0)
                          FROM assignments
                          WHERE assignments.tutor_id = tutors.id
                            AND assignments.status IN ('pending', 'active')
                      )
                ) AS tutors_at_full_capacity
            SQL
        );

        return DashboardStats::fromRow($row ?? []);
    }
}
