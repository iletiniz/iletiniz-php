<?php

declare(strict_types=1);

namespace Iletiniz\Resource;

use Iletiniz\Exception\IletinizError;
use Iletiniz\Http\HttpClient;
use Iletiniz\RequestOptions;

final class MessagesResource
{
    private const MAX_BULK_ITEMS = 200;

    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * Tek bir SMS mesajı gönderir.
     *
     * `body` veya `template` alanlarından **tam olarak biri** verilmelidir.
     * `variables` yalnızca `template` ile birlikte kullanılabilir.
     *
     * @param array{
     *     to: string,
     *     body?: string,
     *     template?: string,
     *     variables?: array<string, string|int|float>,
     *     sender?: string,
     *     provider?: string,
     *     iys?: bool,
     * } $params
     *
     * @return array{
     *     job_id: string,
     *     status: 'sent'|'queued'|'failed',
     *     to: string,
     *     provider: string,
     *     template?: string,
     *     template_key?: string,
     *     error?: array{code: string, message: string},
     *     created_at: string,
     * }
     */
    public function send(array $params, ?RequestOptions $options = null): array
    {
        $this->validateSendParams($params);
        /** @var array{
         *     job_id: string,
         *     status: 'sent'|'queued'|'failed',
         *     to: string,
         *     provider: string,
         *     template?: string,
         *     template_key?: string,
         *     error?: array{code: string, message: string},
         *     created_at: string,
         * } $res
         */
        $res = $this->http->request('POST', '/v1/messages', [], self::stripNulls($params), $options);
        return $res;
    }

    /**
     * Tek istekte birden fazla mesaj gönderir (en fazla 200 öğe).
     *
     * - Üst seviye `template` verildiyse her item'da `body` olmamalı, yalnızca `variables` opsiyoneldir.
     * - Üst seviye `template` yoksa her item'da `body` zorunludur, `variables` kullanılamaz.
     *
     * @param array{
     *     provider?: string,
     *     sender?: string,
     *     template?: string,
     *     iys?: bool,
     *     items: list<array{
     *         to: string,
     *         body?: string,
     *         variables?: array<string, string|int|float>,
     *     }>,
     * } $params
     *
     * @return array{
     *     total: int,
     *     sent: int,
     *     failed: int,
     *     provider: string,
     *     template?: string,
     *     template_key?: string,
     *     created_at: string,
     *     results: list<array{
     *         to: string,
     *         status: 'sent'|'failed',
     *         job_id?: string,
     *         error?: array{code: string, message: string},
     *     }>,
     * }
     */
    public function sendBulk(array $params, ?RequestOptions $options = null): array
    {
        $this->validateBulkParams($params);
        $clean = self::stripNulls($params);
        if (isset($clean['items']) && is_array($clean['items'])) {
            $clean['items'] = array_map(static fn (array $item): array => self::stripNulls($item), $clean['items']);
        }
        /** @var array{
         *     total: int,
         *     sent: int,
         *     failed: int,
         *     provider: string,
         *     template?: string,
         *     template_key?: string,
         *     created_at: string,
         *     results: list<array{
         *         to: string,
         *         status: 'sent'|'failed',
         *         job_id?: string,
         *         error?: array{code: string, message: string},
         *     }>,
         * } $res
         */
        $res = $this->http->request('POST', '/v1/messages/bulk', [], $clean, $options);
        return $res;
    }

    /**
     * Daha önce gönderilmiş bir mesajın güncel durumunu döner.
     *
     * @return array{
     *     job_id: string,
     *     status: 'sent'|'queued'|'failed'|'delivered'|'expired'|'rejected'|'unknown',
     *     to: string,
     *     provider: string,
     *     error?: array{code: string, message: string},
     *     created_at: string,
     *     sent_at: string|null,
     *     delivered_at: string|null,
     * }
     */
    public function retrieve(string $jobId, ?RequestOptions $options = null): array
    {
        if ($jobId === '') {
            throw new IletinizError('jobId boş olamaz.');
        }
        /** @var array{
         *     job_id: string,
         *     status: 'sent'|'queued'|'failed'|'delivered'|'expired'|'rejected'|'unknown',
         *     to: string,
         *     provider: string,
         *     error?: array{code: string, message: string},
         *     created_at: string,
         *     sent_at: string|null,
         *     delivered_at: string|null,
         * } $res
         */
        $res = $this->http->request('GET', '/v1/messages/' . rawurlencode($jobId), [], null, $options);
        return $res;
    }

    /**
     * `retrieve` için alias.
     *
     * @return array{
     *     job_id: string,
     *     status: 'sent'|'queued'|'failed'|'delivered'|'expired'|'rejected'|'unknown',
     *     to: string,
     *     provider: string,
     *     error?: array{code: string, message: string},
     *     created_at: string,
     *     sent_at: string|null,
     *     delivered_at: string|null,
     * }
     */
    public function status(string $jobId, ?RequestOptions $options = null): array
    {
        return $this->retrieve($jobId, $options);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function validateSendParams(array $params): void
    {
        $to = $params['to'] ?? null;
        if (!is_string($to) || strlen($to) < 1 || strlen($to) > 64) {
            throw new IletinizError("'to' alanı 1-64 karakter arasında olmalıdır.");
        }

        $hasBody = isset($params['body']) && $params['body'] !== '' && $params['body'] !== null;
        $hasTemplate = isset($params['template']) && $params['template'] !== '' && $params['template'] !== null;

        if ($hasBody === $hasTemplate) {
            throw new IletinizError("'body' veya 'template' alanlarından tam olarak biri zorunludur.");
        }
        if (isset($params['variables']) && !$hasTemplate) {
            throw new IletinizError("'variables' yalnızca 'template' ile birlikte kullanılabilir.");
        }
        if ($hasBody) {
            $body = $params['body'];
            if (!is_string($body) || strlen($body) < 1 || strlen($body) > 1600) {
                throw new IletinizError("'body' 1-1600 karakter arasında olmalıdır.");
            }
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function validateBulkParams(array $params): void
    {
        $items = $params['items'] ?? null;
        if (!is_array($items) || $items === []) {
            throw new IletinizError("'items' en az bir öğe içermelidir.");
        }
        if (count($items) > self::MAX_BULK_ITEMS) {
            throw new IletinizError(sprintf("'items' en fazla %d öğe içerebilir.", self::MAX_BULK_ITEMS));
        }

        $usingTemplate = isset($params['template']) && $params['template'] !== '' && $params['template'] !== null;

        $i = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new IletinizError(sprintf('items[%d] bir nesne olmalıdır.', $i));
            }
            $to = $item['to'] ?? null;
            if (!is_string($to) || strlen($to) < 1 || strlen($to) > 64) {
                throw new IletinizError(sprintf('items[%d].to 1-64 karakter arasında olmalıdır.', $i));
            }

            if ($usingTemplate) {
                if (array_key_exists('body', $item)) {
                    throw new IletinizError(sprintf("Üst seviye 'template' verildi: items[%d].body kullanılamaz.", $i));
                }
            } else {
                $body = $item['body'] ?? null;
                if (!is_string($body) || strlen($body) < 1) {
                    throw new IletinizError(sprintf("'template' yok: items[%d].body zorunludur.", $i));
                }
                if (array_key_exists('variables', $item)) {
                    throw new IletinizError(sprintf("'template' yok: items[%d].variables kullanılamaz.", $i));
                }
            }
            $i++;
        }
    }

    /**
     * @param array<string, mixed> $arr
     * @return array<string, mixed>
     */
    private static function stripNulls(array $arr): array
    {
        return array_filter($arr, static fn (mixed $v): bool => $v !== null);
    }
}
