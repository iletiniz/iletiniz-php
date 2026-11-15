<?php

declare(strict_types=1);

namespace Iletiniz;

use Iletiniz\Exception\IletinizError;

/**
 * iletiniz webhook isteklerinin imza doğrulaması.
 *
 * İmza: `hex(hmac_sha256(secret, "{timestamp}.{nonce}.{sha256_hex(body)}"))`,
 * başlıklar `X-Iletiniz-Timestamp`, `X-Iletiniz-Nonce`, `X-Iletiniz-Signature`.
 * Gövde ham haliyle (`file_get_contents('php://input')`) doğrulanmalıdır.
 */
final class Webhook
{
    public const DEFAULT_TOLERANCE = 300;

    /**
     * İmza ve zaman damgası geçerliyse true.
     *
     * @param array<array-key, mixed> $headers Başlık adları büyük/küçük harf duyarsız.
     *        `getallheaders()`, PSR-7 `getHeaders()` ya da `$_SERVER` doğrudan verilebilir.
     * @param int $tolerance Kabul edilen saat farkı (sn). 0 = zaman kontrolü yok.
     */
    public static function verify(
        string $payload,
        array $headers,
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE,
        ?int $now = null,
    ): bool {
        if ($secret === '') {
            return false;
        }
        $h = self::normalizeHeaders($headers);
        $ts = $h['x-iletiniz-timestamp'] ?? '';
        $nonce = $h['x-iletiniz-nonce'] ?? '';
        $sig = strtolower($h['x-iletiniz-signature'] ?? '');
        if ($ts === '' || $nonce === '' || $sig === '' || preg_match('/^\d{1,12}$/', $ts) !== 1) {
            return false;
        }
        if ($tolerance > 0 && abs(($now ?? time()) - (int) $ts) > $tolerance) {
            return false;
        }
        $expected = hash_hmac('sha256', $ts . '.' . $nonce . '.' . hash('sha256', $payload), $secret);
        return hash_equals($expected, $sig);
    }

    /**
     * İmzayı doğrular ve olayı dizi olarak döner. Geçersizse `IletinizError` fırlatır.
     *
     * @param array<array-key, mixed> $headers
     * @return array{id: string, event: string, workspace_id: int, occurred_at: string, data: array<string, mixed>}
     */
    public static function parse(
        string $payload,
        array $headers,
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE,
        ?int $now = null,
    ): array {
        if (!self::verify($payload, $headers, $secret, $tolerance, $now)) {
            throw new IletinizError('Webhook imzası geçersiz ya da zaman damgası eski.');
        }
        $event = json_decode($payload, true);
        if (!is_array($event) || !isset($event['event']) || !is_string($event['event'])) {
            throw new IletinizError('Webhook gövdesi geçerli bir olay değil.');
        }
        /** @var array{id: string, event: string, workspace_id: int, occurred_at: string, data: array<string, mixed>} $event */
        return $event;
    }

    /**
     * Başlık adlarını küçük harfe çevirir. `$_SERVER` biçimi (`HTTP_X_ILETINIZ_SIGNATURE`)
     * de kabul edilir.
     *
     * @param array<array-key, mixed> $headers
     * @return array<string, string>
     */
    private static function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $value) {
            if (is_array($value)) {
                $value = $value[0] ?? '';
            }
            if (!is_string($value) && !is_int($value)) {
                continue;
            }
            $key = strtolower((string) $name);
            if (str_starts_with($key, 'http_')) {
                $key = str_replace('_', '-', substr($key, 5));
            }
            $out[$key] = trim((string) $value);
        }
        return $out;
    }
}
