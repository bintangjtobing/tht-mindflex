<?php

declare(strict_types=1);

namespace Mindflex\Service;

use Mindflex\Exception\BusinessRuleException;
use Mindflex\Exception\RecordNotFoundException;
use Mindflex\Model\Assignment;
use Mindflex\Model\AssignmentStatus;
use Mindflex\Repository\AssignmentRepository;
use Mindflex\Repository\StudentRepository;
use Mindflex\Repository\SubjectRepository;
use Mindflex\Repository\TutorRepository;
use PDO;
use Throwable;

/**
 * Aturan pembuatan dan perubahan match.
 *
 * Dashboard lama menyimpan apa pun yang dikirim form. Tidak ada pengecekan budget,
 * kapasitas tutor, status tutor, atau duplikasi. Semua pemeriksaan itu sekarang ada
 * di satu tempat dan dipakai oleh dashboard maupun API.
 */
final class AssignmentService
{
    public function __construct(
        private readonly PDO $connection,
        private readonly AssignmentRepository $assignments,
        private readonly TutorRepository $tutors,
        private readonly StudentRepository $students,
        private readonly SubjectRepository $subjects,
        private readonly PricingService $pricing,
        private readonly float $budgetTolerance = 1.0,
    ) {
    }

    public function create(int $studentId, int $tutorId, int $subjectId, int $weeklyHours): int
    {
        $student = $this->students->find($studentId);

        if ($student === null) {
            throw RecordNotFoundException::for('Student', $studentId);
        }

        $tutor = $this->tutors->find($tutorId);

        if ($tutor === null) {
            throw RecordNotFoundException::for('Tutor', $tutorId);
        }

        if (! $tutor->isActive()) {
            throw new BusinessRuleException(
                BusinessRuleException::TUTOR_INACTIVE,
                sprintf('%s is inactive. Activate the tutor before you create a match.', $tutor->name),
                ['tutor_id' => $tutorId]
            );
        }

        $subject = $this->subjects->find($subjectId);

        if ($subject === null) {
            throw RecordNotFoundException::for('Subject', $subjectId);
        }

        if (! $this->subjects->tutorTeachesSubject($tutorId, $subjectId)) {
            throw new BusinessRuleException(
                BusinessRuleException::SUBJECT_NOT_TAUGHT,
                sprintf('%s does not teach %s.', $tutor->name, $subject['name']),
                ['tutor_id' => $tutorId, 'subject' => $subject['name']]
            );
        }

        if ($this->assignments->hasOpenAssignment($studentId, $tutorId, $subjectId)) {
            throw new BusinessRuleException(
                BusinessRuleException::DUPLICATE_ASSIGNMENT,
                sprintf('%s already has an open %s match with %s.', $student->name, $subject['name'], $tutor->name),
                ['student_id' => $studentId, 'tutor_id' => $tutorId]
            );
        }

        if (! $tutor->hasCapacityFor($weeklyHours)) {
            throw new BusinessRuleException(
                BusinessRuleException::TUTOR_CAPACITY_FULL,
                sprintf(
                    '%s has %d free hours per week and you asked for %d.',
                    $tutor->name,
                    $tutor->remainingWeeklyHours(),
                    $weeklyHours
                ),
                ['tutor_id' => $tutorId, 'remaining_hours' => $tutor->remainingWeeklyHours()]
            );
        }

        $weeklyCost = $this->pricing->quoteFor($tutor, $weeklyHours);
        $remainingBudget = $this->pricing->remainingBudgetFor($student);

        if (! $this->pricing->fitsWithinBudget($weeklyCost, $remainingBudget, $this->budgetTolerance)) {
            throw new BusinessRuleException(
                BusinessRuleException::BUDGET_EXCEEDED,
                sprintf(
                    'This match costs %s per week. %s has %s left from a %s weekly budget.',
                    $weeklyCost->format(),
                    $student->name,
                    $remainingBudget->format(),
                    $student->weeklyBudget->format()
                ),
                [
                    'weekly_cost_cents' => $weeklyCost->cents(),
                    'remaining_budget_cents' => $remainingBudget->cents(),
                ]
            );
        }

        return $this->inTransaction(fn (): int => $this->assignments->create(
            studentId: $studentId,
            tutorId: $tutorId,
            subjectId: $subjectId,
            weeklyHours: $weeklyHours,
            // Tarif dibekukan di sini. Perubahan tarif tutor tidak menyentuh baris ini.
            agreedHourlyRateCents: $tutor->hourlyRate->cents(),
            status: AssignmentStatus::Active,
        ));
    }

    public function complete(int $assignmentId): Assignment
    {
        $assignment = $this->requireAssignment($assignmentId);

        if (! $assignment->status->canBeCompleted()) {
            throw new BusinessRuleException(
                BusinessRuleException::INVALID_STATUS_TRANSITION,
                sprintf('You cannot complete a match that is already %s.', strtolower($assignment->status->label())),
                ['status' => $assignment->status->value]
            );
        }

        $this->assignments->updateStatus($assignmentId, AssignmentStatus::Completed);

        return $this->requireAssignment($assignmentId);
    }

    /**
     * Membatalkan match tidak menghapus barisnya.
     * Tombol lama menjalankan DELETE, sehingga riwayat pendapatan dan jejak audit hilang.
     */
    public function cancel(int $assignmentId): Assignment
    {
        $assignment = $this->requireAssignment($assignmentId);

        if (! $assignment->status->canBeCancelled()) {
            throw new BusinessRuleException(
                BusinessRuleException::INVALID_STATUS_TRANSITION,
                sprintf('You cannot cancel a match that is already %s.', strtolower($assignment->status->label())),
                ['status' => $assignment->status->value]
            );
        }

        $this->assignments->updateStatus($assignmentId, AssignmentStatus::Cancelled);

        return $this->requireAssignment($assignmentId);
    }

    private function requireAssignment(int $assignmentId): Assignment
    {
        $assignment = $this->assignments->find($assignmentId);

        if ($assignment === null) {
            throw RecordNotFoundException::for('Assignment', $assignmentId);
        }

        return $assignment;
    }

    /**
     * @template TResult
     * @param callable(): TResult $operation
     * @return TResult
     */
    private function inTransaction(callable $operation): mixed
    {
        $this->connection->beginTransaction();

        try {
            $result = $operation();
            $this->connection->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }
}
