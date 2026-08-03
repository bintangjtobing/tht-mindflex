<?php

declare(strict_types=1);

namespace Mindflex\Service;

use Mindflex\Model\Student;
use Mindflex\Model\Tutor;
use Mindflex\Support\Money;

/**
 * Aturan harga.
 *
 * Satu keputusan penting ada di sini. Biaya sebuah match memakai tarif yang tercatat
 * pada assignment, bukan tarif tutor hari ini. Dashboard lama membaca tarif terbaru
 * untuk setiap baris, sehingga kenaikan tarif tutor mengubah tagihan dan laporan
 * pendapatan minggu sebelumnya.
 */
final class PricingService
{
    public const DEFAULT_WEEKLY_HOURS = 2;

    public function weeklyCost(Money $hourlyRate, int $weeklyHours): Money
    {
        return $hourlyRate->multiplyBy(max(0, $weeklyHours));
    }

    /**
     * Perkiraan biaya untuk match baru. Tarif diambil saat penawaran dibuat,
     * lalu disimpan pada assignment agar tidak berubah.
     */
    public function quoteFor(Tutor $tutor, int $weeklyHours): Money
    {
        return $this->weeklyCost($tutor->hourlyRate, $weeklyHours);
    }

    /**
     * Sisa budget student setelah dikurangi komitmen yang masih berjalan.
     */
    public function remainingBudgetFor(Student $student): Money
    {
        $remaining = $student->remainingWeeklyBudget();

        return $remaining->isPositive() ? $remaining : Money::zero();
    }

    public function fitsWithinBudget(Money $weeklyCost, Money $remainingBudget, float $tolerance = 1.0): bool
    {
        $allowedCents = (int) floor($remainingBudget->cents() * max(1.0, $tolerance));

        return $weeklyCost->cents() <= $allowedCents;
    }
}
