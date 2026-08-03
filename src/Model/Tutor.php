<?php

declare(strict_types=1);

namespace Mindflex\Model;

use Mindflex\Support\Money;
use Mindflex\Support\Row;

final readonly class Tutor
{
    /**
     * @param list<string> $subjectNames
     * @param list<int> $subjectIds
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public Money $hourlyRate,
        public int $maxWeeklyHours,
        public string $status,
        public float $rating,
        public int $reviewCount,
        public array $subjectNames,
        public array $subjectIds,
        public int $bookedWeeklyHours,
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
            email: Row::string($row, 'email'),
            hourlyRate: Money::fromCents(Row::int($row, 'hourly_rate_cents')),
            maxWeeklyHours: Row::int($row, 'max_weekly_hours', 40),
            status: Row::string($row, 'status', 'inactive'),
            rating: Row::float($row, 'rating'),
            reviewCount: Row::int($row, 'review_count'),
            subjectNames: Row::concatenatedList($row, 'subject_names'),
            subjectIds: array_map(intval(...), Row::concatenatedList($row, 'subject_ids')),
            bookedWeeklyHours: Row::int($row, 'booked_weekly_hours'),
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function remainingWeeklyHours(): int
    {
        return max(0, $this->maxWeeklyHours - $this->bookedWeeklyHours);
    }

    public function hasCapacityFor(int $weeklyHours): bool
    {
        return $weeklyHours <= $this->remainingWeeklyHours();
    }

    public function capacityUsedRatio(): float
    {
        if ($this->maxWeeklyHours <= 0) {
            return 1.0;
        }

        return min(1.0, $this->bookedWeeklyHours / $this->maxWeeklyHours);
    }

    public function hasReviews(): bool
    {
        return $this->reviewCount > 0;
    }

    public function subjectsAsText(): string
    {
        return $this->subjectNames === [] ? 'No subject recorded' : implode(', ', $this->subjectNames);
    }

    public function weeklyCostFor(int $weeklyHours): Money
    {
        return $this->hourlyRate->multiplyBy($weeklyHours);
    }
}
