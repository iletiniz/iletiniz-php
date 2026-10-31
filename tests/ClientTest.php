<?php

declare(strict_types=1);

namespace Iletiniz\Tests;

use Iletiniz\Exception\IletinizError;
use Iletiniz\Exception\IletinizServerError;
use Iletiniz\Http\HttpResponse;
use Iletiniz\Http\TransportInterface;
use Iletiniz\IletinizClient;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    /** @var list<array{method: string, url: string, body: mixed}> */
    private array $calls = [];

    private function client(int $status = 200, string $body = '{}'): IletinizClient
    {
        $this->calls = [];
        $calls = &$this->calls;
        $transport = new class ($calls, $status, $body) implements TransportInterface {
            /** @param list<array{method: string, url: string, body: mixed}> $calls */
            public function __construct(private array &$calls, private int $status, private string $body)
            {
            }

            public function send(string $method, string $url, array $headers, ?string $body, int $timeoutMs): HttpResponse
            {
                $this->calls[] = [
                    'method' => $method,
                    'url' => $url,
                    'body' => $body !== null ? json_decode($body, true) : null,
                ];
                return new HttpResponse($this->status, $this->body, []);
            }
        };
        return new IletinizClient(['apiKey' => 'iltz_test_abc', 'transport' => $transport, 'maxRetries' => 2]);
    }

    public function testWhatsappTemplateSenderScheduledAt(): void
    {
        $c = $this->client(201, '{"scheduled_id":7,"status":"scheduled","kind":"single","scheduled_at":"2026-11-05T06:00:00.000Z"}');
        $res = $c->messages->send([
            'to' => '+905551234567',
            'provider' => 'whatsapp',
            'sender' => '123456789012345',
            'whatsappTemplate' => [
                'name' => 'randevu_hatirlatma',
                'language' => 'tr',
                'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => 'Ayşe']]]],
            ],
            'scheduledAt' => new \DateTimeImmutable('2026-11-05T09:00:00+03:00'),
        ]);
        self::assertSame(7, $res['scheduled_id']);
        self::assertSame('https://api.iletiniz.com/v1/messages', $this->calls[0]['url']);
        self::assertSame('2026-11-05T09:00:00+03:00', $this->calls[0]['body']['scheduledAt']);
        self::assertSame('123456789012345', $this->calls[0]['body']['sender']);
        self::assertSame('randevu_hatirlatma', $this->calls[0]['body']['whatsappTemplate']['name']);
    }

    public function testBodyAndWhatsappTemplateRejected(): void
    {
        $this->expectException(IletinizError::class);
        $this->client()->messages->send([
            'to' => '+905551234567',
            'body' => 'x',
            'whatsappTemplate' => ['name' => 'a', 'language' => 'tr'],
        ]);
    }

    public function testBulkWhatsappComponents(): void
    {
        $c = $this->client(200, '{"total":1,"sent":1,"failed":0,"provider":"whatsapp","created_at":"x","results":[]}');
        $c->messages->sendBulk([
            'provider' => 'whatsapp',
            'whatsappTemplate' => ['name' => 'kargo', 'language' => 'tr'],
            'items' => [[
                'to' => '+905551234567',
                'whatsappComponents' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => 'TR1']]]],
            ]],
        ]);
        self::assertSame('body', $this->calls[0]['body']['items'][0]['whatsappComponents'][0]['type']);

        $this->expectException(IletinizError::class);
        $c->messages->sendBulk(['items' => [['to' => '+905551234567', 'body' => 'x', 'whatsappComponents' => []]]]);
    }

    public function testScheduledAndRemindersPaths(): void
    {
        $c = $this->client(200, '{"data":[],"limit":10,"offset":0}');
        $c->scheduled->list(['status' => 'scheduled', 'limit' => 10]);
        $c->scheduled->cancel(5);
        $c->reminders->create([
            'type' => 'recurring',
            'name' => 'Haftalık',
            'message' => ['to' => '+905551234567', 'body' => 'Merhaba'],
            'frequency' => 'weekly',
            'timeOfDay' => '09:30',
            'dayOfWeek' => 1,
        ]);
        $c->reminders->update(3, [], null, true);
        $c->reminders->delete(3);
        self::assertSame([
            'GET https://api.iletiniz.com/v1/scheduled?status=scheduled&limit=10',
            'DELETE https://api.iletiniz.com/v1/scheduled/5',
            'POST https://api.iletiniz.com/v1/reminders',
            'PATCH https://api.iletiniz.com/v1/reminders/3',
            'DELETE https://api.iletiniz.com/v1/reminders/3',
        ], array_map(static fn (array $x): string => $x['method'] . ' ' . $x['url'], $this->calls));
        self::assertArrayHasKey('endsAt', $this->calls[3]['body']);
        self::assertNull($this->calls[3]['body']['endsAt']);
    }

    public function testQuotaTransferIsNotRetried(): void
    {
        $c = $this->client(503, '{"error":{"code":"x","message":"y"}}');
        try {
            $c->partner->transferQuota(12, 500);
            self::fail('hata bekleniyordu');
        } catch (IletinizServerError $e) {
            // API zarfı {"error":{"code","message"}} çözülür.
            self::assertSame('x', $e->getApiCode());
            self::assertSame('y', $e->getMessage());
            self::assertSame(503, $e->status);
        }
        self::assertCount(1, $this->calls);
        self::assertSame('https://api.iletiniz.com/v1/partner/accounts/12/quota-transfers', $this->calls[0]['url']);
        self::assertSame(['amount' => 500], $this->calls[0]['body']);
    }

    public function testPartnerPaths(): void
    {
        $c = $this->client();
        $c->partner->createAccount(['name' => 'Klinik A', 'externalRef' => 'k-1']);
        $c->partner->createApiKey(12, ['name' => 'prod', 'test' => true]);
        $c->partner->createWhatsappSignupLink(12);
        $c->partner->unsuspendAccount(12);
        self::assertSame([
            'POST https://api.iletiniz.com/v1/partner/accounts',
            'POST https://api.iletiniz.com/v1/partner/accounts/12/api-keys',
            'POST https://api.iletiniz.com/v1/partner/accounts/12/whatsapp-signup',
            'POST https://api.iletiniz.com/v1/partner/accounts/12/unsuspend',
        ], array_map(static fn (array $x): string => $x['method'] . ' ' . $x['url'], $this->calls));
    }

    public function testPartnerApiKeyListAndRevoke(): void
    {
        $key = '{"id":7,"name":"prod","key_prefix":"iltz_live_Ab","scopes":["messages:send"],"sandbox":false,'
            . '"source":"partner","status":"active","created_at":"2026-10-31T10:00:00.000Z","last_used_at":null,"revoked_at":null}';
        $c = $this->client(200, '{"data":[' . $key . ']}');
        $res = $c->partner->listApiKeys(12);
        self::assertSame('iltz_live_Ab', $res['data'][0]['key_prefix']);
        self::assertSame('GET https://api.iletiniz.com/v1/partner/accounts/12/api-keys', $this->calls[0]['method'] . ' ' . $this->calls[0]['url']);

        $c = $this->client(200, str_replace('"active"', '"revoked"', $key));
        $revoked = $c->partner->revokeApiKey(12, 7);
        self::assertSame('revoked', $revoked['status']);
        self::assertSame('DELETE https://api.iletiniz.com/v1/partner/accounts/12/api-keys/7', $this->calls[0]['method'] . ' ' . $this->calls[0]['url']);

        $this->expectException(IletinizError::class);
        $c->partner->revokeApiKey(12, 0);
    }
}
