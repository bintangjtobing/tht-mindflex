<?php

declare(strict_types=1);

namespace Mindflex\Http;

/**
 * Balasan pengalihan.
 * Status 303 memberi tahu browser untuk memakai GET setelah POST, sehingga
 * menekan tombol muat ulang tidak mengirim form dua kali.
 */
final readonly class RedirectResponse
{
    public function __construct(
        public string $location,
        public int $statusCode = 303,
    ) {
    }

    public static function toDashboard(string $queryString = ''): self
    {
        return new self($queryString === '' ? 'index.php' : 'index.php?' . $queryString);
    }

    public static function toLogin(): self
    {
        return new self('index.php?page=login');
    }

    public function send(): void
    {
        if (! headers_sent()) {
            header('Location: ' . $this->location, true, $this->statusCode);
        }
    }
}
