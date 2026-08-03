<?php

declare(strict_types=1);

namespace Mindflex\Service;

use Mindflex\Model\MatchCandidate;
use Mindflex\Model\MatchResult;
use Mindflex\Model\Student;
use Mindflex\Model\Tutor;
use Mindflex\Repository\AssignmentRepository;
use Mindflex\Repository\SubjectRepository;
use Mindflex\Repository\TutorRepository;
use Mindflex\Support\Money;

/**
 * Mesin pencocokan tutor dan student.
 *
 * Versi lama mengambil tutor pertama yang cocok lalu memberi skor 1.0 untuk semua.
 * Pencocokannya memakai LIKE '%kata%', jadi kata "Science" ikut menarik tutor
 * "Computer Science". Pada data contoh, cara itu mengembalikan 15 tutor aktif
 * padahal tidak satu pun mengajar Science.
 */
final class MatchmakingService
{
    /**
     * Bobot skor. Total selalu 1.0.
     */
    private const WEIGHT_RATING = 0.40;
    private const WEIGHT_BUDGET_FIT = 0.35;
    private const WEIGHT_CAPACITY = 0.25;

    /**
     * Rating awal untuk tutor yang belum punya cukup review.
     * Tanpa ini, satu review bintang lima langsung mengalahkan tutor dengan
     * 50 review bernilai 4.8.
     */
    private const RATING_PRIOR_MEAN = 4.0;
    private const RATING_PRIOR_WEIGHT = 5.0;

    public function __construct(
        private readonly TutorRepository $tutors,
        private readonly SubjectRepository $subjects,
        private readonly AssignmentRepository $assignments,
        private readonly PricingService $pricing,
        private readonly float $budgetTolerance = 1.0,
    ) {
    }

    public function findMatches(
        Student $student,
        string $subjectName,
        int $weeklyHours = PricingService::DEFAULT_WEEKLY_HOURS,
        int $limit = 5,
    ): MatchResult {
        $subject = $this->subjects->findBySlug($subjectName);

        if ($subject === null) {
            return MatchResult::subjectNotFound($subjectName, $weeklyHours);
        }

        $availableTutors = $this->tutors->findActiveBySubjectSlug($subject['slug']);
        $remainingBudget = $this->pricing->remainingBudgetFor($student);

        $candidates = [];
        $filteredOut = ['over_budget' => 0, 'no_capacity' => 0, 'already_matched' => 0];

        foreach ($availableTutors as $tutor) {
            if (! $tutor->hasCapacityFor($weeklyHours)) {
                $filteredOut['no_capacity']++;

                continue;
            }

            if ($this->assignments->hasOpenAssignment($student->id, $tutor->id, $subject['id'])) {
                $filteredOut['already_matched']++;

                continue;
            }

            $weeklyCost = $this->pricing->quoteFor($tutor, $weeklyHours);

            if (! $this->pricing->fitsWithinBudget($weeklyCost, $remainingBudget, $this->budgetTolerance)) {
                $filteredOut['over_budget']++;

                continue;
            }

            $candidates[] = $this->scoreCandidate($tutor, $weeklyHours, $weeklyCost, $remainingBudget);
        }

        usort($candidates, static function (MatchCandidate $left, MatchCandidate $right): int {
            return [$right->score, $right->tutor->rating, $left->tutor->hourlyRate->cents()]
                <=> [$left->score, $left->tutor->rating, $right->tutor->hourlyRate->cents()];
        });

        return new MatchResult(
            subjectId: $subject['id'],
            subjectName: $subject['name'],
            weeklyHours: $weeklyHours,
            candidates: array_slice($candidates, 0, max(1, $limit)),
            filteredOut: $filteredOut,
            tutorsTeachingSubject: count($availableTutors),
        );
    }

    private function scoreCandidate(
        Tutor $tutor,
        int $weeklyHours,
        Money $weeklyCost,
        Money $remainingBudget,
    ): MatchCandidate {
        $ratingScore = $this->smoothedRating($tutor) / 5.0;
        $budgetScore = $this->budgetFitScore($weeklyCost, $remainingBudget);
        $capacityScore = $tutor->maxWeeklyHours > 0
            ? $tutor->remainingWeeklyHours() / $tutor->maxWeeklyHours
            : 0.0;

        $breakdown = [
            'rating' => round($ratingScore, 3),
            'budget_fit' => round($budgetScore, 3),
            'capacity' => round($capacityScore, 3),
        ];

        $total = ($ratingScore * self::WEIGHT_RATING)
            + ($budgetScore * self::WEIGHT_BUDGET_FIT)
            + ($capacityScore * self::WEIGHT_CAPACITY);

        return new MatchCandidate(
            tutor: $tutor,
            weeklyHours: $weeklyHours,
            weeklyCost: $weeklyCost,
            score: round($total, 3),
            scoreBreakdown: $breakdown,
            withinBudget: true,
        );
    }

    /**
     * Rating dengan penghalusan Bayesian sederhana.
     */
    private function smoothedRating(Tutor $tutor): float
    {
        // Rating hasil impor tanpa review tetap dipakai, namun bobotnya satu suara saja.
        $evidenceCount = $tutor->reviewCount > 0
            ? (float) $tutor->reviewCount
            : ($tutor->rating > 0 ? 1.0 : 0.0);

        $totalScore = ($tutor->rating * $evidenceCount) + (self::RATING_PRIOR_MEAN * self::RATING_PRIOR_WEIGHT);
        $totalWeight = $evidenceCount + self::RATING_PRIOR_WEIGHT;

        return $totalScore / $totalWeight;
    }

    /**
     * Semakin kecil porsi budget yang terpakai, semakin tinggi skornya.
     */
    private function budgetFitScore(Money $weeklyCost, Money $remainingBudget): float
    {
        if (! $remainingBudget->isPositive()) {
            return 0.0;
        }

        $usedRatio = $weeklyCost->ratioTo($remainingBudget);

        if (! is_finite($usedRatio)) {
            return 0.0;
        }

        return max(0.0, min(1.0, 1.0 - $usedRatio));
    }
}
