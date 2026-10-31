<?php

declare(strict_types=1);

namespace Iletiniz\Resource;

use Iletiniz\Http\HttpClient;
use Iletiniz\RequestOptions;

/**
 * `scheduledAt` ile oluşturulan ileri tarihli gönderimler (`/v1/scheduled`).
 *
 * Kayıt biçimi:
 * array{id: int, kind: 'single'|'bulk', status: 'scheduled'|'processing'|'sent'|'failed'|'canceled',
 *       scheduled_at: string, reminder_id: int|null, recipients: int, sandbox?: true,
 *       result: array<string, mixed>|null, error_code: string|null, created_at: string}
 */
final class ScheduledResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * @param array{status?: 'scheduled'|'processing'|'sent'|'failed'|'canceled', limit?: int, offset?: int} $params
     * @return array{data: list<array<string, mixed>>, limit: int, offset: int}
     */
    public function list(array $params = [], ?RequestOptions $options = null): array
    {
        /** @var array{data: list<array<string, mixed>>, limit: int, offset: int} $res */
        $res = $this->http->request('GET', '/v1/scheduled', Util::listQuery($params), null, $options);
        return $res;
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieve(int $id, ?RequestOptions $options = null): array
    {
        Util::assertId($id);
        /** @var array<string, mixed> $res */
        $res = $this->http->request('GET', '/v1/scheduled/' . $id, [], null, $options);
        return $res;
    }

    /**
     * Bekleyen (`scheduled`) gönderimi iptal eder; aksi halde 409 `not_cancelable`.
     *
     * @return array<string, mixed>
     */
    public function cancel(int $id, ?RequestOptions $options = null): array
    {
        Util::assertId($id);
        /** @var array<string, mixed> $res */
        $res = $this->http->request('DELETE', '/v1/scheduled/' . $id, [], null, $options);
        return $res;
    }
}
