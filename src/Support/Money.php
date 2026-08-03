<?php

declare(strict_types=1);

namespace Mindflex\Support;

use InvalidArgumentException;

/**
 * Uang disimpan sebagai integer cent.
 * Kolom REAL pada skema lama membuat 3 x 35.5 bisa meleset beberapa sen saat direkap.
 */
final class Money
{
    private function __construct(private readonly int $cents)
    {
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Ubah angka desimal menjadi cent tanpa lewat aritmetika float.
     */
    public static function fromDecimal(string|int|float $amount): self
    {
        $normalized = is_float($amount)
            ? number_format($amount, 4, '.', '')
            : trim((string) $amount);

        $normalized = str_replace(',', '', $normalized);

        if (preg_match('/^-?\d+(\.\d+)?$/', $normalized) !== 1) {
            throw new InvalidArgumentException(sprintf('Nilai uang tidak valid: "%s".', $normalized));
        }

        $isNegative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');

        [$wholePart, $fractionPart] = array_pad(explode('.', $normalized, 2), 2, '0');
        $fractionPart = substr(str_pad($fractionPart, 3, '0'), 0, 3);

        $cents = ((int) $wholePart * 100) + (int) substr($fractionPart, 0, 2);

        if ((int) $fractionPart[2] >= 5) {
            $cents++;
        }

        return new self($isNegative ? -$cents : $cents);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function multiplyBy(int $factor): self
    {
        return new self($this->cents * $factor);
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->cents > $other->cents;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    /**
     * Rasio terhadap nilai lain. Dipakai untuk menilai kecocokan harga dengan budget.
     */
    public function ratioTo(self $other): float
    {
        if ($other->cents === 0) {
            return $this->cents === 0 ? 0.0 : INF;
        }

        return $this->cents / $other->cents;
    }

    public function toDecimal(): float
    {
        return $this->cents / 100;
    }

    public function format(string $currency = 'USD'): string
    {
        $symbols = ['USD' => '$', 'IDR' => 'Rp', 'EUR' => '€', 'SGD' => 'S$'];
        $symbol = $symbols[strtoupper($currency)] ?? ($currency . ' ');

        $formatted = number_format(abs($this->cents) / 100, 2);

        return ($this->cents < 0 ? '-' : '') . $symbol . $formatted;
    }
}
