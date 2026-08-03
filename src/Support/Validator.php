<?php

declare(strict_types=1);

namespace Mindflex\Support;

use Mindflex\Exception\ValidationException;

/**
 * Validator input yang mengumpulkan seluruh error lalu melempar sekali di akhir.
 * Pengguna melihat semua kesalahan dalam satu kali submit.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @param array<string, mixed> $input
     */
    public function __construct(private readonly array $input)
    {
    }

    public function requiredString(string $field, int $maxLength = 255, string $label = ''): string
    {
        $label = $label !== '' ? $label : $field;
        $value = $this->input[$field] ?? null;

        if (! is_string($value) || trim($value) === '') {
            $this->errors[$field] = sprintf('Isi kolom %s.', $label);

            return '';
        }

        $value = trim($value);

        if (mb_strlen($value) > $maxLength) {
            $this->errors[$field] = sprintf('Kolom %s maksimal %d karakter.', $label, $maxLength);

            return '';
        }

        return $value;
    }

    public function email(string $field): string
    {
        $value = $this->input[$field] ?? null;

        if (! is_string($value) || trim($value) === '') {
            $this->errors[$field] = 'Isi alamat email.';

            return '';
        }

        $value = strtolower(trim($value));

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[$field] = 'Format email tidak valid.';

            return '';
        }

        return $value;
    }

    /**
     * Kembalikan nilai uang dalam cent.
     */
    public function moneyCents(string $field, int $minimumCents, int $maximumCents, string $label = ''): int
    {
        $label = $label !== '' ? $label : $field;
        $value = $this->input[$field] ?? null;

        if (! is_string($value) && ! is_numeric($value)) {
            $this->errors[$field] = sprintf('Isi kolom %s.', $label);

            return 0;
        }

        $rawValue = is_string($value) ? trim($value) : (string) $value;

        if ($rawValue === '' || preg_match('/^\d+(\.\d{1,2})?$/', $rawValue) !== 1) {
            $this->errors[$field] = sprintf('Kolom %s harus angka positif dengan maksimal dua desimal.', $label);

            return 0;
        }

        $cents = Money::fromDecimal($rawValue)->cents();

        if ($cents < $minimumCents || $cents > $maximumCents) {
            $this->errors[$field] = sprintf(
                'Kolom %s harus antara %s dan %s.',
                $label,
                Money::fromCents($minimumCents)->format(),
                Money::fromCents($maximumCents)->format()
            );

            return 0;
        }

        return $cents;
    }

    public function integer(string $field, int $minimum, int $maximum, string $label = ''): int
    {
        $label = $label !== '' ? $label : $field;
        $value = $this->input[$field] ?? null;

        if (! is_scalar($value) || preg_match('/^-?\d+$/', trim((string) $value)) !== 1) {
            $this->errors[$field] = sprintf('Kolom %s harus berupa angka bulat.', $label);

            return 0;
        }

        $parsed = (int) $value;

        if ($parsed < $minimum || $parsed > $maximum) {
            $this->errors[$field] = sprintf('Kolom %s harus antara %d dan %d.', $label, $minimum, $maximum);

            return 0;
        }

        return $parsed;
    }

    public function id(string $field, string $label = ''): int
    {
        return $this->integer($field, 1, PHP_INT_MAX, $label !== '' ? $label : $field);
    }

    /**
     * Pecah daftar mata pelajaran dari teks dipisah koma menjadi array unik.
     *
     * @return list<string>
     */
    public function subjectList(string $field, int $maximumSubjects = 10): array
    {
        $value = $this->input[$field] ?? null;

        if (! is_string($value) || trim($value) === '') {
            $this->errors[$field] = 'Isi minimal satu mata pelajaran.';

            return [];
        }

        $subjectNames = [];

        foreach (explode(',', $value) as $rawSubject) {
            $subjectName = trim($rawSubject);

            if ($subjectName === '') {
                continue;
            }

            if (mb_strlen($subjectName) > 60) {
                $this->errors[$field] = 'Nama mata pelajaran maksimal 60 karakter.';

                return [];
            }

            // Pertahankan penulisan pertama. "Math, math" menjadi satu entri: Math.
            $subjectNames[strtolower($subjectName)] ??= $subjectName;
        }

        if ($subjectNames === []) {
            $this->errors[$field] = 'Isi minimal satu mata pelajaran.';

            return [];
        }

        if (count($subjectNames) > $maximumSubjects) {
            $this->errors[$field] = sprintf('Maksimal %d mata pelajaran per tutor.', $maximumSubjects);

            return [];
        }

        return array_values($subjectNames);
    }

    /**
     * @param list<string> $allowedValues
     */
    public function inList(string $field, array $allowedValues, string $label = ''): string
    {
        $label = $label !== '' ? $label : $field;
        $value = $this->input[$field] ?? null;

        if (! is_string($value) || ! in_array($value, $allowedValues, true)) {
            $this->errors[$field] = sprintf('Kolom %s hanya boleh: %s.', $label, implode(', ', $allowedValues));

            return '';
        }

        return $value;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function assertValid(): void
    {
        if ($this->errors !== []) {
            throw new ValidationException($this->errors);
        }
    }
}
