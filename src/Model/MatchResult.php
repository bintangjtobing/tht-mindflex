<?php

declare(strict_types=1);

namespace Mindflex\Model;

/**
 * Hasil pencocokan lengkap dengan alasan tutor tersaring.
 * Admin perlu tahu bahwa 12 tutor tersedia namun 9 di antaranya melewati budget,
 * bukan sekadar menerima satu nama tanpa penjelasan.
 */
final readonly class MatchResult
{
    /**
     * @param list<MatchCandidate> $candidates
     * @param array<string, int> $filteredOut
     */
    public function __construct(
        public ?int $subjectId,
        public ?string $subjectName,
        public int $weeklyHours,
        public array $candidates,
        public array $filteredOut,
        public int $tutorsTeachingSubject,
    ) {
    }

    public static function subjectNotFound(string $requestedSubject, int $weeklyHours): self
    {
        return new self(null, $requestedSubject, $weeklyHours, [], [], 0);
    }

    public function hasMatch(): bool
    {
        return $this->candidates !== [];
    }

    public function best(): ?MatchCandidate
    {
        return $this->candidates[0] ?? null;
    }

    public function filteredOutTotal(): int
    {
        return array_sum($this->filteredOut);
    }

    /**
     * Penjelasan singkat saat tidak ada kandidat yang lolos.
     */
    public function explainNoMatch(): string
    {
        if ($this->tutorsTeachingSubject === 0) {
            return sprintf('No active tutor teaches %s right now.', $this->subjectName ?? 'this subject');
        }

        $reasons = [];

        if (($this->filteredOut['over_budget'] ?? 0) > 0) {
            $reasons[] = sprintf('%d cost more than the remaining weekly budget', $this->filteredOut['over_budget']);
        }

        if (($this->filteredOut['no_capacity'] ?? 0) > 0) {
            $reasons[] = sprintf('%d have no free hours left', $this->filteredOut['no_capacity']);
        }

        if (($this->filteredOut['already_matched'] ?? 0) > 0) {
            $reasons[] = sprintf('%d already work with this student', $this->filteredOut['already_matched']);
        }

        if ($reasons === []) {
            return 'No tutor passed the matching rules.';
        }

        return sprintf(
            '%d tutors teach this subject. %s.',
            $this->tutorsTeachingSubject,
            ucfirst(implode(', ', $reasons))
        );
    }
}
