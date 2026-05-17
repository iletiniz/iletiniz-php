<?php

declare(strict_types=1);

namespace Iletiniz\Http;

use Iletiniz\Exception\IletinizConnectionError;
use Iletiniz\Exception\IletinizTimeoutError;

interface TransportInterface
{
    /**
     * @param 'GET'|'POST'|'PUT'|'DELETE'|'PATCH' $method
     * @param array<string, string>                $headers
     *
     * @throws IletinizTimeoutError    İstek timeout süresinde tamamlanamadıysa.
     * @throws IletinizConnectionError Diğer ağ kaynaklı hatalar.
     */
    public function send(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeoutMs,
    ): HttpResponse;
}
