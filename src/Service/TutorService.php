<?php

declare(strict_types=1);

namespace Mindflex\Service;

use Mindflex\Exception\RecordNotFoundException;
use Mindflex\Exception\ValidationException;
use Mindflex\Model\Tutor;
use Mindflex\Repository\SubjectRepository;
use Mindflex\Repository\TutorRepository;
use Mindflex\Support\Clock;
use PDO;
use Throwable;

final class TutorService
{
    public function __construct(
        private readonly PDO $connection,
        private readonly TutorRepository $tutors,
        private readonly SubjectRepository $subjects,
    ) {
    }

    /**
     * Daftarkan tutor baru.
     * Rating tidak diterima dari input. Form lama menerimanya, sehingga siapa pun
     * bisa mendaftar dengan nilai 5.0 dan langsung unggul di hasil pencocokan.
     *
     * @param list<string> $subjectNames
     */
    public function register(
        string $name,
        string $email,
        int $hourlyRateCents,
        array $subjectNames,
        int $maxWeeklyHours = 40,
    ): int {
        if ($this->tutors->emailIsTaken($email)) {
            throw new ValidationException(['email' => 'Another tutor already uses this email address.']);
        }

        $this->connection->beginTransaction();

        try {
            $subjectIds = $this->subjects->firstOrCreateMany($subjectNames);
            $tutorId = $this->tutors->create($name, $email, $hourlyRateCents, $subjectIds, $maxWeeklyHours);

            $this->connection->commit();

            return $tutorId;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Ubah tarif tutor. Perubahan hanya berlaku untuk match berikutnya.
     * Match berjalan memakai tarif yang tersimpan di barisnya sendiri.
     */
    public function changeHourlyRate(int $tutorId, int $hourlyRateCents): Tutor
    {
        $tutor = $this->tutors->find($tutorId);

        if ($tutor === null) {
            throw RecordNotFoundException::for('Tutor', $tutorId);
        }

        $this->tutors->updateHourlyRate($tutorId, $hourlyRateCents);

        $updatedTutor = $this->tutors->find($tutorId);

        return $updatedTutor ?? $tutor;
    }

    public function addReview(int $tutorId, ?int $assignmentId, int $score, ?string $comment = null): void
    {
        if ($this->tutors->find($tutorId) === null) {
            throw RecordNotFoundException::for('Tutor', $tutorId);
        }

        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO tutor_reviews (tutor_id, assignment_id, score, comment, created_at)
            VALUES (:tutor_id, :assignment_id, :score, :comment, :created_at)
            SQL
        );
        $statement->execute([
            'tutor_id' => $tutorId,
            'assignment_id' => $assignmentId,
            'score' => max(1, min(5, $score)),
            'comment' => $comment,
            'created_at' => Clock::nowUtc(),
        ]);

        $this->tutors->refreshRating($tutorId);
    }
}
