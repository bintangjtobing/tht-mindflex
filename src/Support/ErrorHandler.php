<?php

declare(strict_types=1);

namespace Mindflex\Support;

use ErrorException;
use Throwable;

/**
 * Penanganan error terpusat.
 *
 * Kedua file lama memasang display_errors = 1 tanpa syarat, sehingga pesan
 * database beserta path server tampil di halaman publik saat terjadi kesalahan.
 * Sekarang tampilan detail hanya aktif ketika APP_DEBUG bernilai true.
 */
final class ErrorHandler
{
    public static function register(Config $config): void
    {
        $isDebug = $config->isDebug();

        error_reporting(E_ALL);
        ini_set('display_errors', $isDebug ? '1' : '0');
        ini_set('display_startup_errors', $isDebug ? '1' : '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $exception) use ($config, $isDebug): void {
            self::log($config, $exception);

            if (! headers_sent()) {
                http_response_code(500);
            }

            if ($isDebug) {
                echo '<pre style="color:#000000;background:#ffffff;padding:16px;white-space:pre-wrap;">';
                echo e($exception::class . ': ' . $exception->getMessage()) . "\n\n";
                echo e($exception->getTraceAsString());
                echo '</pre>';

                return;
            }

            echo '<p style="color:#000000;font-family:system-ui,sans-serif;padding:16px;">'
                . 'Something went wrong. The team has the details in the log.'
                . '</p>';
        });
    }

    public static function log(Config $config, Throwable $exception): void
    {
        $logDirectory = $config->projectRoot() . '/storage/logs';

        if (! is_dir($logDirectory) && ! mkdir($logDirectory, 0o775, true) && ! is_dir($logDirectory)) {
            return;
        }

        $entry = sprintf(
            '[%s] %s: %s in %s:%d%s%s%s',
            Clock::nowUtc(),
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            PHP_EOL,
            $exception->getTraceAsString(),
            PHP_EOL . PHP_EOL
        );

        file_put_contents($logDirectory . '/app.log', $entry, FILE_APPEND | LOCK_EX);
    }
}
