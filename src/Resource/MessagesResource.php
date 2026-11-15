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
     * Tek bir mesaj gönderir (SMS, Telegram veya WhatsApp).
     *
     * `body`, `template`, `whatsappTemplate` veya `whatsappMessage` alanlarından **tam olarak biri**
     * verilmelidir. `variables` yalnızca `template` ile birlikte kullanılabilir.
     * `whatsappMessage`: son 24 saat içinde yazmış kişiye şablonsuz oturum mesajı (metin, buton,
     * liste, bağlantı butonu, görsel, video, belge, ses, konum); zamanlanamaz.
     * `sender`: SMS'te gönderici başlığı, WhatsApp'ta gönderici numaranın Meta `phone_number_id` değeri.
     * `scheduledAt` (ISO 8601 dizesi veya DateTimeInterface) verilirse mesaj şimdi gönderilmez;
     * yanıt `{scheduled_id, status: 'scheduled', kind, scheduled_at}` olur.
     *
     * @param array{
     *     to: string,
     *     body?: string,
     *     template?: string,
     *     variables?: array<string, string|int|float>,
     *     whatsappTemplate?: array{name: string, language: string, components?: list<array<string, mixed>>},
     *     whatsappMessage?: array{
     *         type: 'text'|'buttons'|'list'|'cta_url'|'image'|'video'|'document'|'audio'|'location',
     *         text?: string,
     *         previewUrl?: bool,
     *         header?: string|array{type: 'text'|'image'|'video'|'document', text?: string, link?: string},
     *         body?: string,
     *         footer?: string,
     *         buttons?: list<array{id: string, title: string}>,
     *         buttonText?: string,
     *         sections?: list<array{title: string, rows: list<array{id: string, title: string, description?: string}>}>,
     *         displayText?: string,
     *         url?: string,
     *         link?: string,
     *         caption?: string,
     *         filename?: string,
     *         latitude?: float,
     *         longitude?: float,
     *         name?: string,
     *         address?: string,
     *         replyTo?: string,
     *     },
     *     sender?: string,
     *     provider?: string,
     *     fallback?: list<string>,
     *     iys?: bool|'bilgilendirme'|'bireysel'|'tacir',
     *     idempotencyKey?: string,
     *     scheduledAt?: string|\DateTimeInterface,
     * } $params
     *
     * @return array{
     *     job_id: string,
     *     status: 'sent'|'queued'|'failed',
     *     to: string,
     *     provider: string,
     *     template?: string,
     *     template_key?: string,
     *     whatsapp_template?: string,
     *     error?: array{code: string, message: string},
     *     attempts?: list<array{provider: string, status: 'sent'|'failed', error?: array{code: string, message: string}}>,
     *     sandbox?: true,
     *     created_at: string,
     * }|array{scheduled_id: int, status: 'scheduled', kind: 'single', scheduled_at: string, sandbox?: true}
     *
     * Not: `whatsappMessage.header` buton ve bağlantı butonunda nesne
     * (`['type' => 'text', 'text' => ...]` ya da `['type' => 'image', 'link' => ...]`), listede düz metindir.
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
         *     whatsapp_template?: string,
         *     error?: array{code: string, message: string},
         *     attempts?: list<array{provider: string, status: 'sent'|'failed', error?: array{code: string, message: string}}>,
         *     sandbox?: true,
         *     created_at: string,
         * }|array{scheduled_id: int, status: 'scheduled', kind: 'single', scheduled_at: string, sandbox?: true} $res
         */
        $res = $this->http->request('POST', '/v1/messages', [], Util::prepare($params), $options);
        return $res;
    }

    /**
     * Tek istekte birden fazla mesaj gönderir (en fazla 200 öğe).
     *
     * - Üst seviye `template` verildiyse her item'da `body` olmamalı, yalnızca `variables` opsiyoneldir.
     * - Üst seviye `whatsappTemplate` verildiyse item'larda `body`/`variables` olmamalı; alıcı başına
     *   değişkenler `items[].whatsappComponents` ile verilir (üst seviye `components` varsayılandır).
     * - İkisi de yoksa her item'da `body` zorunludur, `variables` kullanılamaz.
     * - `scheduledAt` verilirse yanıt `{scheduled_id, status: 'scheduled', kind: 'bulk', scheduled_at}` olur.
     *
     * @param array{
     *     provider?: string,
     *     sender?: string,
     *     template?: string,
     *     whatsappTemplate?: array{name: string, language: string, components?: list<array<string, mixed>>},
     *     fallback?: list<string>,
     *     iys?: bool|'bilgilendirme'|'bireysel'|'tacir',
     *     idempotencyKey?: string,
     *     scheduledAt?: string|\DateTimeInterface,
     *     items: list<array{
     *         to: string,
     *         body?: string,
     *         variables?: array<string, string|int|float>,
     *         whatsappComponents?: list<array<string, mixed>>,
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
     *     whatsapp_template?: string,
     *     sandbox?: true,
     *     created_at: string,
     *     results: list<array{
     *         to: string,
     *         status: 'sent'|'failed',
     *         job_id?: string,
     *         error?: array{code: string, message: string},
     *         delivered_via?: string,
     *     }>,
     * }|array{scheduled_id: int, status: 'scheduled', kind: 'bulk', scheduled_at: string, sandbox?: true}
     */
    public function sendBulk(array $params, ?RequestOptions $options = null): array
    {
        $this->validateBulkParams($params);
        $clean = Util::prepare($params);
        if (isset($clean['items']) && is_array($clean['items'])) {
            $clean['items'] = array_map(static fn (array $item): array => Util::stripNulls($item), $clean['items']);
        }
        /** @var array{
         *     total: int,
         *     sent: int,
         *     failed: int,
         *     provider: string,
         *     template?: string,
         *     template_key?: string,
         *     whatsapp_template?: string,
         *     sandbox?: true,
         *     created_at: string,
         *     results: list<array{
         *         to: string,
         *         status: 'sent'|'failed',
         *         job_id?: string,
         *         error?: array{code: string, message: string},
         *         delivered_via?: string,
         *     }>,
         * }|array{scheduled_id: int, status: 'scheduled', kind: 'bulk', scheduled_at: string, sandbox?: true} $res
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

        $hasWa = isset($params['whatsappTemplate']);
        $hasWaMessage = isset($params['whatsappMessage']);

        if (((int) $hasBody + (int) $hasTemplate + (int) $hasWa + (int) $hasWaMessage) !== 1) {
            throw new IletinizError(
                "'body', 'template', 'whatsappTemplate' veya 'whatsappMessage' alanlarından tam olarak biri zorunludur.",
            );
        }
        if ($hasWa) {
            self::validateWhatsappTemplate($params['whatsappTemplate']);
        }
        if ($hasWaMessage) {
            if (isset($params['scheduledAt'])) {
                throw new IletinizError(
                    "'whatsappMessage' zamanlanamaz (24 saat penceresine bağlı). Zamanlı gönderim için 'whatsappTemplate' kullanın.",
                );
            }
            self::validateWhatsappMessage($params['whatsappMessage']);
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
        $usingWa = isset($params['whatsappTemplate']);
        if ($usingTemplate && $usingWa) {
            throw new IletinizError("'template' ve 'whatsappTemplate' birlikte kullanılamaz.");
        }
        if ($usingWa) {
            self::validateWhatsappTemplate($params['whatsappTemplate']);
        }

        $i = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new IletinizError(sprintf('items[%d] bir nesne olmalıdır.', $i));
            }
            $to = $item['to'] ?? null;
            if (!is_string($to) || strlen($to) < 1 || strlen($to) > 64) {
                throw new IletinizError(sprintf('items[%d].to 1-64 karakter arasında olmalıdır.', $i));
            }

            if (array_key_exists('whatsappComponents', $item) && !$usingWa) {
                throw new IletinizError(sprintf("items[%d].whatsappComponents yalnızca 'whatsappTemplate' ile kullanılabilir.", $i));
            }
            if ($usingWa) {
                if (array_key_exists('body', $item) || array_key_exists('variables', $item)) {
                    throw new IletinizError(sprintf("'whatsappTemplate' verildi: items[%d].body ve variables kullanılamaz.", $i));
                }
            } elseif ($usingTemplate) {
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
     * Temel yapı ve Meta sayı sınırları (buton 1-3, liste toplam 10 satır). Uzunluk ve
     * diğer kuralları API doğrular (400 `validation_error`).
     */
    private static function validateWhatsappMessage(mixed $m): void
    {
        if (!is_array($m)) {
            throw new IletinizError("'whatsappMessage' bir nesne olmalıdır.");
        }
        $type = $m['type'] ?? null;
        $types = ['text', 'buttons', 'list', 'cta_url', 'image', 'video', 'document', 'audio', 'location'];
        if (!is_string($type) || !in_array($type, $types, true)) {
            throw new IletinizError(sprintf("'whatsappMessage.type' şunlardan biri olmalıdır: %s.", implode(', ', $types)));
        }
        if ($type === 'buttons') {
            $buttons = $m['buttons'] ?? null;
            if (!is_array($buttons) || count($buttons) < 1 || count($buttons) > 3) {
                throw new IletinizError("'whatsappMessage.buttons' 1-3 buton içermelidir.");
            }
        }
        if ($type === 'list') {
            $sections = $m['sections'] ?? null;
            if (!is_array($sections) || count($sections) < 1 || count($sections) > 10) {
                throw new IletinizError("'whatsappMessage.sections' 1-10 bölüm içermelidir.");
            }
            $rows = 0;
            foreach ($sections as $s) {
                $rows += is_array($s) && is_array($s['rows'] ?? null) ? count($s['rows']) : 0;
            }
            if ($rows < 1 || $rows > 10) {
                throw new IletinizError("'whatsappMessage' listesinde toplam 1-10 satır olmalıdır.");
            }
        }
        if (in_array($type, ['image', 'video', 'document', 'audio'], true)) {
            $link = $m['link'] ?? null;
            if (!is_string($link) || !str_starts_with($link, 'https://')) {
                throw new IletinizError("'whatsappMessage.link' https:// ile başlayan bir adres olmalıdır.");
            }
        }
    }

    private static function validateWhatsappTemplate(mixed $t): void
    {
        if (!is_array($t)) {
            throw new IletinizError("'whatsappTemplate' bir nesne olmalıdır.");
        }
        $name = $t['name'] ?? null;
        if (!is_string($name) || preg_match('/^[a-z0-9_]{1,512}$/', $name) !== 1) {
            throw new IletinizError("'whatsappTemplate.name' yalnızca a-z, 0-9 ve _ içerebilir.");
        }
        $lang = $t['language'] ?? null;
        if (!is_string($lang) || preg_match('/^[a-z]{2,3}(_[A-Z]{2,4})?$/', $lang) !== 1) {
            throw new IletinizError("'whatsappTemplate.language' geçersiz (ör. tr, en_US).");
        }
    }
}
