<?php

declare(strict_types=1);

namespace Iletiniz\Resource;

use Iletiniz\Exception\IletinizError;
use Iletiniz\Http\HttpClient;
use Iletiniz\RequestOptions;

/**
 * Hatırlatma kuralları (`/v1/reminders`).
 *
 * one_time:  ['type' => 'one_time', 'name', 'message', 'eventAt', 'offsetsMinutes' => [1440, 120]]
 * recurring: ['type' => 'recurring', 'name', 'message', 'frequency' => 'daily'|'weekly'|'monthly',
 *             'timeOfDay' => '09:30', 'dayOfWeek'?, 'dayOfMonth'?, 'timezone'?, 'endsAt'?]
 * message:   ['to', 'body'|'template'+'variables'|'whatsappTemplate', 'sender'?, 'provider'?, 'iys'?]
 * Tarihler ISO 8601 dizesi veya DateTimeInterface olabilir.
 */
final class RemindersResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function create(array $params, ?RequestOptions $options = null): array
    {
        $type = $params['type'] ?? null;
        if ($type !== 'one_time' && $type !== 'recurring') {
            throw new IletinizError("'type' alanı 'one_time' veya 'recurring' olmalıdır.");
        }
        if ($type === 'recurring') {
            $freq = $params['frequency'] ?? null;
            if ($freq === 'weekly' && !isset($params['dayOfWeek'])) {
                throw new IletinizError("Haftalık tekrar için 'dayOfWeek' zorunlu.");
            }
            if ($freq === 'monthly' && !isset($params['dayOfMonth'])) {
                throw new IletinizError("Aylık tekrar için 'dayOfMonth' zorunlu.");
            }
        }
        /** @var array<string, mixed> $res */
        $res = $this->http->request('POST', '/v1/reminders', [], Util::prepare($params), $options);
        return $res;
    }

    /**
     * @param array{status?: 'active'|'paused'|'completed'|'canceled', limit?: int, offset?: int} $params
     * @return array{data: list<array<string, mixed>>, limit: int, offset: int}
     */
    public function list(array $params = [], ?RequestOptions $options = null): array
    {
        /** @var array{data: list<array<string, mixed>>, limit: int, offset: int} $res */
        $res = $this->http->request('GET', '/v1/reminders', Util::listQuery($params), null, $options);
        return $res;
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieve(int $id, ?RequestOptions $options = null): array
    {
        Util::assertId($id);
        /** @var array<string, mixed> $res */
        $res = $this->http->request('GET', '/v1/reminders/' . $id, [], null, $options);
        return $res;
    }

    /**
     * Kuralı günceller; bekleyen gönderimler yeni ayarlarla yeniden planlanır.
     * Null değerli alanlar gövdeden atılır; bitiş tarihini kaldırmak için `clearEndsAt: true` verin.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function update(int $id, array $params, ?RequestOptions $options = null, bool $clearEndsAt = false): array
    {
        Util::assertId($id);
        $body = Util::prepare($params);
        if ($clearEndsAt) {
            $body['endsAt'] = null;
        }
        if ($body === []) {
            throw new IletinizError('En az bir alan güncellenmeli.');
        }
        /** @var array<string, mixed> $res */
        $res = $this->http->request('PATCH', '/v1/reminders/' . $id, [], $body, $options);
        return $res;
    }

    /**
     * Kuralı iptal eder (`canceled`) ve bekleyen gönderimlerini siler.
     *
     * @return array<string, mixed>
     */
    public function delete(int $id, ?RequestOptions $options = null): array
    {
        Util::assertId($id);
        /** @var array<string, mixed> $res */
        $res = $this->http->request('DELETE', '/v1/reminders/' . $id, [], null, $options);
        return $res;
    }
}
