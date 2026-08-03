<?php

declare(strict_types=1);

namespace Mindflex\Http;

use Mindflex\Support\Csrf;

/**
 * Pembungkus tipis untuk superglobal.
 * Controller tidak menyentuh $_GET dan $_POST langsung, sehingga logikanya bisa diuji.
 */
final readonly class Request
{
    /**
     * @param array<string, mixed> $queryParameters
     * @param array<string, mixed> $bodyParameters
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $method,
        public array $queryParameters,
        public array $bodyParameters,
        public array $headers,
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        return new self(
            method: is_string($method) ? strtoupper($method) : 'GET',
            queryParameters: $_GET,
            bodyParameters: $_POST,
            headers: self::readHeaders(),
        );
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function query(string $key, string $default = ''): string
    {
        $value = $this->queryParameters[$key] ?? null;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function queryInt(string $key, int $default = 0): int
    {
        $value = $this->query($key);

        return $value === '' ? $default : (int) $value;
    }

    public function input(string $key, string $default = ''): string
    {
        $value = $this->bodyParameters[$key] ?? null;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /**
     * Ambil aksi dari body untuk POST, dari query string untuk GET.
     */
    public function action(): string
    {
        return $this->isPost() ? $this->input('action') : $this->query('action');
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function csrfToken(): string
    {
        return $this->input(Csrf::FIELD_NAME);
    }

    public function expectsJson(): bool
    {
        return str_contains($this->header('accept'), 'application/json');
    }

    /**
     * @return array<string, string>
     */
    private static function readHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            }
        }

        return $headers;
    }
}
