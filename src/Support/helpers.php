<?php

declare(strict_types=1);

if (! function_exists('e')) {
    /**
     * Escape nilai sebelum dicetak ke HTML. Semua view wajib lewat sini.
     */
    function e(string|int|float|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('base_path')) {
    /**
     * Path absolut ke root proyek.
     */
    function base_path(string $relativePath = ''): string
    {
        $projectRoot = dirname(__DIR__, 2);

        if ($relativePath === '') {
            return $projectRoot;
        }

        return $projectRoot . DIRECTORY_SEPARATOR . ltrim($relativePath, '/\\');
    }
}
