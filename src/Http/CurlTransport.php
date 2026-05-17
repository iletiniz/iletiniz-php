<?php

declare(strict_types=1);

namespace Iletiniz\Http;

use Iletiniz\Exception\IletinizConnectionError;
use Iletiniz\Exception\IletinizTimeoutError;

final class CurlTransport implements TransportInterface
{
    public function send(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeoutMs,
    ): HttpResponse {
        $ch = curl_init();
        if ($ch === false) {
            throw new IletinizConnectionError('cURL handle oluşturulamadı.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $responseHeaders = [];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => $timeoutMs,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_HEADERFUNCTION => static function ($_ch, string $line) use (&$responseHeaders): int {
                $len = strlen($line);
                $trimmed = trim($line);
                if ($trimmed === '' || stripos($trimmed, 'HTTP/') === 0) {
                    return $len;
                }
                $colonPos = strpos($trimmed, ':');
                if ($colonPos === false) {
                    return $len;
                }
                $key = strtolower(trim(substr($trimmed, 0, $colonPos)));
                $value = trim(substr($trimmed, $colonPos + 1));
                $responseHeaders[$key] = $value;
                return $len;
            },
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $errno = curl_errno($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($errno === CURLE_OPERATION_TIMEOUTED) {
                throw new IletinizTimeoutError("İstek {$timeoutMs}ms içinde tamamlanamadı.");
            }
            throw new IletinizConnectionError($err !== '' ? $err : 'Bağlantı hatası');
        }

        /** @var int $status */
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return new HttpResponse($status, (string) $raw, $responseHeaders);
    }
}
