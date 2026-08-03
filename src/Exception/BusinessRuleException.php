<?php

declare(strict_types=1);

namespace Mindflex\Exception;

use RuntimeException;

/**
 * Dilempar saat input valid tetapi melanggar aturan bisnis.
 * Contoh: biaya mingguan melewati budget student, atau jam tutor sudah penuh.
 */
final class BusinessRuleException extends RuntimeException
{
    public const BUDGET_EXCEEDED = 'budget_exceeded';
    public const TUTOR_CAPACITY_FULL = 'tutor_capacity_full';
    public const TUTOR_INACTIVE = 'tutor_inactive';
    public const SUBJECT_NOT_TAUGHT = 'subject_not_taught';
    public const DUPLICATE_ASSIGNMENT = 'duplicate_assignment';
    public const INVALID_STATUS_TRANSITION = 'invalid_status_transition';

    /**
     * @param array<string, string|int|float> $context
     */
    public function __construct(
        private readonly string $ruleCode,
        string $message,
        private readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public function ruleCode(): string
    {
        return $this->ruleCode;
    }

    /**
     * @return array<string, string|int|float>
     */
    public function context(): array
    {
        return $this->context;
    }
}
