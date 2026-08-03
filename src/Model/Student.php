<?php

declare(strict_types=1);

namespace Mindflex\Model;

use Mindflex\Support\Money;
use Mindflex\Support\Row;

final readonly class Student
{
    public function __construct(
        public int $id,
        public string $name,
        public string $gradeLevel,
        public Money $weeklyBudget,
        public Money $committedWeeklyCost,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: Row::int($row, 'id'),
            name: Row::string($row, 'name'),
            gradeLevel: Row::string($row, 'grade_level'),
            weeklyBudget: Money::fromCents(Row::int($row, 'weekly_budget_cents')),
            committedWeeklyCost: Money::fromCents(Row::int($row, 'committed_weekly_cost_cents')),
        );
    }

    /**
     * Sisa budget setelah dikurangi match yang masih berjalan.
     */
    public function remainingWeeklyBudget(): Money
    {
        return $this->weeklyBudget->subtract($this->committedWeeklyCost);
    }

    public function isOverBudget(): bool
    {
        return $this->committedWeeklyCost->isGreaterThan($this->weeklyBudget);
    }

    public function budgetUsedRatio(): float
    {
        return $this->committedWeeklyCost->ratioTo($this->weeklyBudget);
    }
}
