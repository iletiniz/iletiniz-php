<?php

declare(strict_types=1);

namespace Iletiniz\Tests;

use Iletiniz\Exception\IletinizAPIError;
use Iletiniz\Exception\IletinizError;
use Iletiniz\Http\HttpResponse;
use Iletiniz\Http\TransportInterface;
use Iletiniz\IletinizClient;
use Iletiniz\Resource\WhatsappResource;
use Iletiniz\Webhook;
use PHPUnit\Framework\TestCase;

final class WhatsappTest extends TestCase
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    private array $calls = [];

    /**
     * @param array<string, string> $headers
     */
    private function client(int $status = 200, string $body = '{}', array $headers = []): IletinizClient
    {
        $this->calls = [];
        $calls = &$this->calls;
        $transport = new class ($calls, $status, $body, $headers) implements TransportInterface {
            /**
             * @param list<array{method: string, url: string, headers: array<string, string>, body: ?string}> $calls
             * @param array<string, string> $headers
             */
            public function __construct(
                private array &$calls,
                private int $status,
                private string $body,
                private array $headers,
            ) {
            }

            public function send(string $method, string $url, array $headers, ?string $body, int $timeoutMs): HttpResponse
            {
                $this->calls[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
                return new HttpResponse($this->status, $this->body, $this->headers);
            }
        };
        return new IletinizClient(['apiKey' => 'iltz_live_abc', 'transport' => $transport, 'maxRetries' => 0]);
    }

    public function testSendsButtonsSessionMessage(): void
    {
        $c = $this->client(201, '{"job_id":"j1","status":"sent","to":"+905551234567","provider":"whatsapp","created_at":"x"}');
        $res = $c->messages->send([
            'to' => '+905551234567',
            'provider' => 'whatsapp',
            'whatsappMessage' => [
                'type' => 'buttons',
                'body' => 'Ne yapmak istersiniz?',
                'buttons' => [['id' => 'siparis', 'title' => 'Sipariş durumu']],
                'replyTo' => 'wamid.IN1',
            ],
        ]);
        self::assertSame('sent', $res['status']);
        $sent = json_decode((string) $this->calls[0]['body'], true);
        self::assertSame('buttons', $sent['whatsappMessage']['type']);
        self::assertSame('wamid.IN1', $sent['whatsappMessage']['replyTo']);
        self::assertArrayNotHasKey('body', $sent);
    }

    public function testWhatsappMessageIsExclusiveWithBody(): void
    {
        $this->expectException(IletinizError::class);
        $this->client()->messages->send([
            'to' => '+905551234567',
            'body' => 'x',
            'whatsappMessage' => ['type' => 'text', 'text' => 'y'],
        ]);
    }

    public function testWhatsappMessageCannotBeScheduled(): void
    {
        $this->expectException(IletinizError::class);
        $this->expectExceptionMessage('zamanlanamaz');
        $this->client()->messages->send([
            'to' => '+905551234567',
            'whatsappMessage' => ['type' => 'text', 'text' => 'y'],
            'scheduledAt' => '2026-12-01T09:00:00+03:00',
        ]);
    }

    public function testRejectsFourButtonsAndElevenRows(): void
    {
        $c = $this->client();
        $btn = static fn (string $id): array => ['id' => $id, 'title' => $id];
        try {
            $c->messages->send([
                'to' => '+905551234567',
                'whatsappMessage' => ['type' => 'buttons', 'body' => 'x', 'buttons' => [$btn('a'), $btn('b'), $btn('c'), $btn('d')]],
            ]);
            self::fail('4 buton reddedilmeliydi');
        } catch (IletinizError $e) {
            self::assertStringContainsString('1-3', $e->getMessage());
        }
        $rows = array_map(static fn (int $i): array => ['id' => "r$i", 'title' => "R$i"], range(1, 11));
        $this->expectException(IletinizError::class);
        $c->messages->send([
            'to' => '+905551234567',
            'whatsappMessage' => ['type' => 'list', 'body' => 'x', 'buttonText' => 'Seç', 'sections' => [['title' => 'A', 'rows' => $rows]]],
        ]);
        self::assertCount(0, $this->calls);
    }

    public function testDownloadMediaReturnsBinaryAndFilename(): void
    {
        $c = $this->client(200, "%PDF-\x00\x01", [
            'content-type' => 'application/pdf',
            'content-disposition' => "attachment; filename=\"_deme.pdf\"; filename*=UTF-8''%C3%96deme.pdf",
        ]);
        $m = $c->whatsapp->downloadMedia('wamid.HBgM/abc=');
        self::assertSame("%PDF-\x00\x01", $m['content']);
        self::assertSame('application/pdf', $m['contentType']);
        self::assertSame('Ödeme.pdf', $m['filename']);
        self::assertSame('https://api.iletiniz.com/v1/whatsapp/messages/wamid.HBgM%2Fabc%3D/media', $this->calls[0]['url']);
        self::assertSame('*/*', $this->calls[0]['headers']['Accept']);
    }

    public function testDownloadMediaExpiredRaisesApiError(): void
    {
        $c = $this->client(410, '{"error":{"code":"media_expired","message":"Medya artık yok"}}');
        try {
            $c->whatsapp->downloadMedia('wamid.X');
            self::fail('410 bekleniyordu');
        } catch (IletinizAPIError $e) {
            self::assertSame(410, $e->getStatus());
            self::assertSame('media_expired', $e->getApiCode());
        }
    }

    public function testSaveMediaWritesFile(): void
    {
        $c = $this->client(200, 'IMG', ['content-type' => 'image/jpeg']);
        $path = tempnam(sys_get_temp_dir(), 'iltz');
        self::assertIsString($path);
        try {
            self::assertSame(3, $c->whatsapp->saveMedia('wamid.X', $path));
            self::assertSame('IMG', file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }

    public function testFilenameFromPlainDisposition(): void
    {
        self::assertSame('a.pdf', WhatsappResource::filenameFrom('attachment; filename="a.pdf"'));
        self::assertNull(WhatsappResource::filenameFrom(null));
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function signed(string $secret, string $payload, int $ts): array
    {
        $nonce = '5e8a3c2b1f9d7e6a4b2c1d0e9f8a7b6c';
        $sig = hash_hmac('sha256', $ts . '.' . $nonce . '.' . hash('sha256', $payload), $secret);
        return [$payload, [
            'X-Iletiniz-Timestamp' => (string) $ts,
            'X-Iletiniz-Nonce' => $nonce,
            'X-Iletiniz-Signature' => $sig,
        ]];
    }

    public function testWebhookVerifyAcceptsValidAndRejectsTampered(): void
    {
        $now = 1_800_000_000;
        [$payload, $headers] = $this->signed('whsec_x', '{"id":"e1","event":"message.received","workspace_id":1,"occurred_at":"t","data":{"text":"Merhaba"}}', $now);
        self::assertTrue(Webhook::verify($payload, $headers, 'whsec_x', 300, $now));
        self::assertFalse(Webhook::verify($payload . ' ', $headers, 'whsec_x', 300, $now));
        self::assertFalse(Webhook::verify($payload, $headers, 'yanlis', 300, $now));
        self::assertFalse(Webhook::verify($payload, $headers, 'whsec_x', 300, $now + 301));
        self::assertTrue(Webhook::verify($payload, $headers, 'whsec_x', 0, $now + 9999));
        self::assertFalse(Webhook::verify($payload, [], 'whsec_x', 300, $now));
    }

    public function testWebhookVerifyAcceptsServerStyleAndPsrHeaders(): void
    {
        $now = 1_800_000_000;
        [$payload, $h] = $this->signed('s', '{"event":"message.echo"}', $now);
        $server = [
            'HTTP_X_ILETINIZ_TIMESTAMP' => $h['X-Iletiniz-Timestamp'],
            'HTTP_X_ILETINIZ_NONCE' => $h['X-Iletiniz-Nonce'],
            'HTTP_X_ILETINIZ_SIGNATURE' => $h['X-Iletiniz-Signature'],
            'REQUEST_TIME' => $now,
            'argv' => [],
        ];
        self::assertTrue(Webhook::verify($payload, $server, 's', 300, $now));
        $psr = array_map(static fn (string $v): array => [$v], $h);
        self::assertTrue(Webhook::verify($payload, $psr, 's', 300, $now));
    }

    public function testWebhookParse(): void
    {
        $now = 1_800_000_000;
        [$payload, $h] = $this->signed('s', '{"id":"e1","event":"message.received","workspace_id":1,"occurred_at":"t","data":{"type":"text"}}', $now);
        $event = Webhook::parse($payload, $h, 's', 300, $now);
        self::assertSame('message.received', $event['event']);
        self::assertSame('text', $event['data']['type']);

        $this->expectException(IletinizError::class);
        Webhook::parse($payload, $h, 'baska', 300, $now);
    }
}
