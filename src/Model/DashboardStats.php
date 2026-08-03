<?php

declare(strict_types=1);

namespace Mindflex\Model;

use Mindflex\Support\Money;
use Mindflex\Support\Row;

final readonly class DashboardStats
{
    public function __construct(
        public int $totalTutors,
        public int $activeTutors,
        public int $totalStudents,
        public int $activeAssignments,
        public Money $weeklyRevenue,
        public int $assignmentsOverBudget,
        public int $tutorsAtFullCapacity,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            totalTutors: Row::int($row, 'total_tutors'),
            activeTutors: Row::int($row, 'active_tutors'),
            totalStudents: Row::int($row, 'total_students'),
            activeAssignments: Row::int($row, 'active_assignments'),
            weeklyRevenue: Money::fromCents(Row::int($row, 'weekly_revenue_cents')),
            assignmentsOverBudget: Row::int($row, 'assignments_over_budget'),
            tutorsAtFullCapacity: Row::int($row, 'tutors_at_full_capacity'),
        );
    }

    public function hasBudgetRisk(): bool
    {
        return $this->assignmentsOverBudget > 0;
    }
}
