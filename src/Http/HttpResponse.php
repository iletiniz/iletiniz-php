<?php

declare(strict_types=1);

namespace Iletiniz\Http;

final class HttpResponse
{
    /**
     * @param array<string, string> $headers Header anahtarları küçük harfe normalize edilmiş olmalı.
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers,
    ) {
    }

    public function getHeader(string $name): ?string
    {
        $key = strtolower($name);
        return $this->headers[$key] ?? null;
    }
}
