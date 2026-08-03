<?php

declare(strict_types=1);

namespace Mindflex\Repository;

use Mindflex\Support\Database;
use PDO;

/**
 * Katalog mata pelajaran. Slug memakai huruf kecil supaya "math" dan "Math"
 * dianggap satu entri.
 */
final class SubjectRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /**
     * @return list<array{id: int, name: string, slug: string, tutor_count: int}>
     */
    public function allWithTutorCount(): array
    {
        $rows = Database::fetchAll(
            $this->connection,
            <<<'SQL'
            SELECT
                subjects.id,
                subjects.name,
                subjects.slug,
                COUNT(tutor_subjects.tutor_id) AS tutor_count
            FROM subjects
            LEFT JOIN tutor_subjects ON tutor_subjects.subject_id = subjects.id
            LEFT JOIN tutors ON tutors.id = tutor_subjects.tutor_id AND tutors.status = 'active'
            GROUP BY subjects.id
            ORDER BY subjects.name
            SQL
        );

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'tutor_count' => (int) $row['tutor_count'],
            ],
            $rows
        );
    }

    /**
     * @return array{id: int, name: string, slug: string}|null
     */
    public function find(int $subjectId): ?array
    {
        $statement = $this->connection->prepare('SELECT id, name, slug FROM subjects WHERE id = :id');
        $statement->execute(['id' => $subjectId]);

        return self::hydrate($statement->fetch());
    }

    /**
     * @return array{id: int, name: string, slug: string}|null
     */
    public function findBySlug(string $slug): ?array
    {
        $statement = $this->connection->prepare('SELECT id, name, slug FROM subjects WHERE slug = :slug');
        $statement->execute(['slug' => self::toSlug($slug)]);

        return self::hydrate($statement->fetch());
    }

    /**
     * @return array{id: int, name: string, slug: string}|null
     */
    private static function hydrate(mixed $row): ?array
    {
        if (! is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
        ];
    }

    public function findIdBySlug(string $slug): ?int
    {
        $subject = $this->findBySlug($slug);

        return $subject === null ? null : $subject['id'];
    }

    /**
     * Ambil id mata pelajaran, buat baru jika belum ada.
     */
    public function firstOrCreate(string $name): int
    {
        $existingId = $this->findIdBySlug($name);

        if ($existingId !== null) {
            return $existingId;
        }

        $statement = $this->connection->prepare('INSERT INTO subjects (name, slug) VALUES (:name, :slug)');
        $statement->execute(['name' => trim($name), 'slug' => self::toSlug($name)]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param list<string> $names
     * @return list<int>
     */
    public function firstOrCreateMany(array $names): array
    {
        return array_map($this->firstOrCreate(...), $names);
    }

    public function tutorTeachesSubject(int $tutorId, int $subjectId): bool
    {
        $statement = $this->connection->prepare(
            'SELECT 1 FROM tutor_subjects WHERE tutor_id = :tutor_id AND subject_id = :subject_id LIMIT 1'
        );
        $statement->execute(['tutor_id' => $tutorId, 'subject_id' => $subjectId]);

        return $statement->fetchColumn() !== false;
    }

    private static function toSlug(string $name): string
    {
        return strtolower(trim($name));
    }
}
