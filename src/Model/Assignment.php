<?php

declare(strict_types=1);

namespace Mindflex\Model;

use Mindflex\Support\Money;
use Mindflex\Support\Row;

final readonly class Assignment
{
    public function __construct(
        public int $id,
        public int $studentId,
        public string $studentName,
        public Money $studentWeeklyBudget,
        public int $tutorId,
        public string $tutorName,
        public ?string $subjectName,
        public int $weeklyHours,
        public Money $agreedHourlyRate,
        public Money $currentTutorHourlyRate,
        public AssignmentStatus $status,
        public string $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: Row::int($row, 'id'),
            studentId: Row::int($row, 'student_id'),
            studentName: Row::string($row, 'student_name'),
            studentWeeklyBudget: Money::fromCents(Row::int($row, 'student_weekly_budget_cents')),
            tutorId: Row::int($row, 'tutor_id'),
            tutorName: Row::string($row, 'tutor_name'),
            subjectName: Row::nullableString($row, 'subject_name'),
            weeklyHours: Row::int($row, 'weekly_hours'),
            agreedHourlyRate: Money::fromCents(Row::int($row, 'hourly_rate_cents')),
            currentTutorHourlyRate: Money::fromCents(Row::int($row, 'current_tutor_rate_cents')),
            status: AssignmentStatus::fromDatabase(Row::string($row, 'status', 'pending')),
            createdAt: Row::string($row, 'created_at'),
        );
    }

    /**
     * Biaya memakai tarif yang disepakati saat match dibuat.
     */
    public function weeklyCost(): Money
    {
        return $this->agreedHourlyRate->multiplyBy($this->weeklyHours);
    }

    /**
     * Biaya seandainya memakai tarif tutor hari ini. Dipakai untuk menunjukkan selisih.
     */
    public function weeklyCostAtCurrentRate(): Money
    {
        return $this->currentTutorHourlyRate->multiplyBy($this->weeklyHours);
    }

    public function tutorRateHasChanged(): bool
    {
        return $this->agreedHourlyRate->cents() !== $this->currentTutorHourlyRate->cents();
    }

    public function exceedsStudentBudget(): bool
    {
        return $this->weeklyCost()->isGreaterThan($this->studentWeeklyBudget);
    }

    public function budgetOverrun(): Money
    {
        return $this->weeklyCost()->subtract($this->studentWeeklyBudget);
    }

    public function subjectLabel(): string
    {
        return $this->subjectName ?? 'Not recorded';
    }
}
