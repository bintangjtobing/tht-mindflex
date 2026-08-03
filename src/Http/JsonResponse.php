<?php

declare(strict_types=1);

namespace Mindflex\Http;

/**
 * Amplop JSON tunggal untuk seluruh endpoint.
 *
 * API lama membalas 200 untuk semua kondisi, kadang JSON dan kadang teks biasa.
 * Klien tidak bisa membedakan sukses dari gagal tanpa membaca isi pesan.
 * Sekarang jawaban sukses selalu punya kunci "data" dan jawaban gagal selalu
 * punya kunci "error" beserta kode HTTP yang sesuai.
 */
final readonly class JsonResponse
{
    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        public int $statusCode,
        public array $payload,
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function success(mixed $data, array $meta = [], int $statusCode = 200): self
    {
        $payload = ['data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return new self($statusCode, $payload);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function error(string $code, string $message, int $statusCode, array $details = []): self
    {
        $error = ['code' => $code, 'message' => $message];

        if ($details !== []) {
            $error['details'] = $details;
        }

        return new self($statusCode, ['error' => $error]);
    }

    public function toJson(): string
    {
        return json_encode($this->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ?: '{"error":{"code":"encoding_failed","message":"Response could not be encoded."}}';
    }

    public function send(): void
    {
        if (! headers_sent()) {
            http_response_code($this->statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }

        echo $this->toJson();
    }
}
