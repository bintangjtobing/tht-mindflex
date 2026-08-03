<?php

declare(strict_types=1);

namespace Mindflex\Http;

use RuntimeException;
use Throwable;

/**
 * Renderer template berbasis PHP biasa.
 * Tampilan pindah keluar dari file logika, jadi SQL dan HTML tidak lagi bercampur
 * dalam satu berkas 600 baris.
 */
final class View
{
    public function __construct(private readonly string $viewsPath)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = $this->capture($template, $data);

        if ($layout === null) {
            return $content;
        }

        return $this->capture($layout, [...$data, 'content' => $content]);
    }

    /**
     * Render potongan tampilan tanpa layout. Dipakai dari dalam template lain.
     *
     * @param array<string, mixed> $data
     */
    public function partial(string $template, array $data = []): string
    {
        return $this->capture($template, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function capture(string $template, array $data): string
    {
        $templatePath = $this->viewsPath . '/' . $template . '.php';

        if (! is_file($templatePath)) {
            throw new RuntimeException(sprintf('Template %s tidak ditemukan.', $template));
        }

        // Template menerima $view supaya bisa memanggil partial tanpa global.
        $data['view'] = $this;

        ob_start();

        try {
            (static function (string $path, array $variables): void {
                extract($variables, EXTR_SKIP);
                require $path;
            })($templatePath, $data);
        } catch (Throwable $exception) {
            ob_end_clean();

            throw $exception;
        }

        return (string) ob_get_clean();
    }
}
