<?php

declare(strict_types=1);

namespace Mindflex\Repository;

use Mindflex\Model\Tutor;
use Mindflex\Support\Clock;
use Mindflex\Support\Database;
use PDO;

/**
 * Semua akses tabel tutors.
 * Mata pelajaran dan jam terpakai ikut dalam satu query, jadi halaman daftar tidak
 * memanggil database sekali per baris seperti versi lama.
 */
final class TutorRepository
{
    private const BASE_SELECT = <<<'SQL'
        SELECT
            tutors.id,
            tutors.name,
            tutors.email,
            tutors.hourly_rate_cents,
            tutors.max_weekly_hours,
            tutors.status,
            tutors.rating,
            tutors.review_count,
            COALESCE(subject_list.names, '') AS subject_names,
            COALESCE(subject_list.ids, '') AS subject_ids,
            COALESCE(booked.weekly_hours, 0) AS booked_weekly_hours
        FROM tutors
        LEFT JOIN (
            SELECT
                tutor_subjects.tutor_id,
                GROUP_CONCAT(subjects.name, '|') AS names,
                GROUP_CONCAT(subjects.id, '|') AS ids
            FROM tutor_subjects
            JOIN subjects ON subjects.id = tutor_subjects.subject_id
            GROUP BY tutor_subjects.tutor_id
        ) AS subject_list ON subject_list.tutor_id = tutors.id
        LEFT JOIN (
            SELECT tutor_id, SUM(weekly_hours) AS weekly_hours
            FROM assignments
            WHERE status IN ('pending', 'active')
            GROUP BY tutor_id
        ) AS booked ON booked.tutor_id = tutors.id
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    /**
     * @return array{tutors: list<Tutor>, total: int, page: int, perPage: int, lastPage: int}
     */
    public function paginate(string $searchTerm = '', int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(5, $perPage));
        $searchTerm = trim($searchTerm);

        $filterSql = '';
        $bindings = [];

        if ($searchTerm !== '') {
            $filterSql = <<<'SQL'
                WHERE (
                    tutors.name LIKE :pattern ESCAPE '\'
                    OR tutors.email LIKE :pattern ESCAPE '\'
                    OR EXISTS (
                        SELECT 1
                        FROM tutor_subjects
                        JOIN subjects ON subjects.id = tutor_subjects.subject_id
                        WHERE tutor_subjects.tutor_id = tutors.id
                          AND subjects.name LIKE :pattern ESCAPE '\'
                    )
                )
                SQL;
            $bindings['pattern'] = '%' . self::escapeLikeTerm($searchTerm) . '%';
        }

        $countStatement = $this->connection->prepare('SELECT COUNT(*) FROM tutors ' . $filterSql);
        $countStatement->execute($bindings);
        $total = (int) $countStatement->fetchColumn();

        $statement = $this->connection->prepare(
            self::BASE_SELECT . ' ' . $filterSql . ' ORDER BY tutors.name LIMIT :limit OFFSET :offset'
        );

        foreach ($bindings as $name => $value) {
            $statement->bindValue($name, $value, PDO::PARAM_STR);
        }

        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return [
            'tutors' => array_map(Tutor::fromRow(...), $rows),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function find(int $tutorId): ?Tutor
    {
        $statement = $this->connection->prepare(self::BASE_SELECT . ' WHERE tutors.id = :id');
        $statement->execute(['id' => $tutorId]);

        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch();

        return $row === false ? null : Tutor::fromRow($row);
    }

    /**
     * Tutor aktif yang mengajar mata pelajaran tertentu.
     * Pencocokan memakai slug persis, bukan LIKE '%kata%'.
     *
     * @return list<Tutor>
     */
    public function findActiveBySubjectSlug(string $subjectSlug): array
    {
        $statement = $this->connection->prepare(
            self::BASE_SELECT . <<<'SQL'
                 WHERE tutors.status = 'active'
                   AND EXISTS (
                       SELECT 1
                       FROM tutor_subjects
                       JOIN subjects ON subjects.id = tutor_subjects.subject_id
                       WHERE tutor_subjects.tutor_id = tutors.id
                         AND subjects.slug = :slug
                   )
                 ORDER BY tutors.rating DESC, tutors.hourly_rate_cents ASC
                SQL
        );
        $statement->execute(['slug' => strtolower(trim($subjectSlug))]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return array_map(Tutor::fromRow(...), $rows);
    }

    /**
     * @return list<Tutor>
     */
    public function listActive(): array
    {
        $rows = Database::fetchAll(
            $this->connection,
            self::BASE_SELECT . " WHERE tutors.status = 'active' ORDER BY tutors.name"
        );

        return array_map(Tutor::fromRow(...), $rows);
    }

    public function emailIsTaken(string $email): bool
    {
        $statement = $this->connection->prepare('SELECT 1 FROM tutors WHERE LOWER(email) = :email LIMIT 1');
        $statement->execute(['email' => strtolower(trim($email))]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param list<int> $subjectIds
     */
    public function create(
        string $name,
        string $email,
        int $hourlyRateCents,
        array $subjectIds,
        int $maxWeeklyHours = 40,
    ): int {
        $timestamp = Clock::nowUtc();

        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO tutors (name, email, hourly_rate_cents, max_weekly_hours, status, rating, review_count, created_at, updated_at)
            VALUES (:name, :email, :hourly_rate_cents, :max_weekly_hours, 'active', 0, 0, :created_at, :updated_at)
            SQL
        );
        $statement->execute([
            'name' => $name,
            'email' => strtolower($email),
            'hourly_rate_cents' => $hourlyRateCents,
            'max_weekly_hours' => $maxWeeklyHours,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $tutorId = (int) $this->connection->lastInsertId();

        $pivotStatement = $this->connection->prepare(
            'INSERT OR IGNORE INTO tutor_subjects (tutor_id, subject_id) VALUES (:tutor_id, :subject_id)'
        );

        foreach ($subjectIds as $subjectId) {
            $pivotStatement->execute(['tutor_id' => $tutorId, 'subject_id' => $subjectId]);
        }

        return $tutorId;
    }

    public function updateHourlyRate(int $tutorId, int $hourlyRateCents): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE tutors SET hourly_rate_cents = :rate, updated_at = :updated_at WHERE id = :id'
        );
        $statement->execute([
            'rate' => $hourlyRateCents,
            'updated_at' => Clock::nowUtc(),
            'id' => $tutorId,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * Hitung ulang rating dari tabel review.
     */
    public function refreshRating(int $tutorId): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            UPDATE tutors
            SET rating = COALESCE((SELECT ROUND(AVG(score), 2) FROM tutor_reviews WHERE tutor_id = :id), 0),
                review_count = (SELECT COUNT(*) FROM tutor_reviews WHERE tutor_id = :id),
                updated_at = :updated_at
            WHERE id = :id
            SQL
        );
        $statement->execute(['id' => $tutorId, 'updated_at' => Clock::nowUtc()]);
    }

    /**
     * Netralkan karakter wildcard supaya pencarian "100%" tidak berubah arti.
     */
    private static function escapeLikeTerm(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}
