<?php

declare(strict_types=1);

namespace Iletiniz;

final class RequestOptions
{
    /**
     * @param array<string, string>|null $headers Bu isteğe özel HTTP başlıkları
     * @param int|null                   $timeoutMs Bu isteğe özel timeout (ms). Client default'unu ezer.
     */
    public function __construct(
        public readonly ?int $timeoutMs = null,
        public readonly ?array $headers = null,
    ) {
    }
}
