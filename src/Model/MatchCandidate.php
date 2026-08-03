<?php

declare(strict_types=1);

namespace Mindflex\Model;

use Mindflex\Support\Money;

/**
 * Satu kandidat tutor beserta alasan angkanya.
 * API lama mengembalikan match_score 1.0 untuk setiap hasil, sehingga admin tidak
 * punya dasar untuk membandingkan dua tutor.
 */
final readonly class MatchCandidate
{
    /**
     * @param array<string, float> $scoreBreakdown
     */
    public function __construct(
        public Tutor $tutor,
        public int $weeklyHours,
        public Money $weeklyCost,
        public float $score,
        public array $scoreBreakdown,
        public bool $withinBudget,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $currency = 'USD'): array
    {
        return [
            'tutor' => [
                'id' => $this->tutor->id,
                'name' => $this->tutor->name,
                'hourly_rate' => $this->tutor->hourlyRate->toDecimal(),
                'hourly_rate_formatted' => $this->tutor->hourlyRate->format($currency),
                'rating' => $this->tutor->rating,
                'review_count' => $this->tutor->reviewCount,
                'subjects' => $this->tutor->subjectNames,
                'remaining_weekly_hours' => $this->tutor->remainingWeeklyHours(),
            ],
            'proposed_hours' => $this->weeklyHours,
            'weekly_cost' => $this->weeklyCost->toDecimal(),
            'weekly_cost_formatted' => $this->weeklyCost->format($currency),
            'within_budget' => $this->withinBudget,
            'match_score' => $this->score,
            'score_breakdown' => $this->scoreBreakdown,
        ];
    }
}
