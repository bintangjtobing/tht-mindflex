<?php

declare(strict_types=1);

namespace Mindflex\Model;

/**
 * Kode lama menyimpan status sebagai '1' dan '2' tanpa keterangan.
 * Enum ini membuat nilainya jelas dan menutup transisi yang tidak masuk akal.
 */
enum AssignmentStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public static function fromDatabase(string $value): self
    {
        return self::tryFrom($value) ?? self::Pending;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Status yang masih memakai jam tutor dan budget student.
     */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Active;
    }

    public function canBeCompleted(): bool
    {
        return $this === self::Active || $this === self::Pending;
    }

    public function canBeCancelled(): bool
    {
        return $this->isOpen();
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Active => 'badge-active',
            self::Pending => 'badge-pending',
            self::Completed => 'badge-completed',
            self::Cancelled => 'badge-cancelled',
        };
    }
}
