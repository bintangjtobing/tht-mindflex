<?php

declare(strict_types=1);

namespace Mindflex\Exception;

use RuntimeException;

/**
 * Dilempar saat input dari form atau API tidak lolos validasi.
 */
final class ValidationException extends RuntimeException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Input tidak valid: ' . implode(' ', $errors));
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstMessage(): string
    {
        $messages = array_values($this->errors);

        return $messages[0] ?? 'Input tidak valid.';
    }
}
