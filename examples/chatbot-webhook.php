<?php

declare(strict_types=1);

/**
 * Basit WhatsApp sohbet botu: iletiniz webhook alıcısı.
 *
 * Panelde webhook endpoint'i için "WhatsApp mesajı alındı" (message.received) ve
 * "Telefondan gönderildi" (message.echo) olaylarını açıkça seçin; seçilmezse gönderilmez.
 *
 * Akış:
 *  - İmza doğrulanır (ILETINIZ_WEBHOOK_SECRET).
 *  - message.echo: işletme telefondan yazdı, bu numara için bot 12 saat susar.
 *  - message.received: buton/liste seçimine göre yanıt; belge ya da görsel geldiyse indirilir.
 *
 * Gerçek uygulamada susturma bilgisini veritabanınızda (ör. MySQL) tutun; burada dosya kullanılır.
 * Webhook'a hızlı (5 sn içinde) 2xx dönün; uzun işleri kuyruğa alın.
 */

require __DIR__ . '/../vendor/autoload.php';

use Iletiniz\Exception\IletinizError;
use Iletiniz\IletinizClient;
use Iletiniz\Webhook;

const MUTE_SECONDS = 12 * 3600;

$payload = (string) file_get_contents('php://input');

try {
    $event = Webhook::parse($payload, $_SERVER, (string) getenv('ILETINIZ_WEBHOOK_SECRET'));
} catch (IletinizError $e) {
    http_response_code(401);
    exit;
}

$client = new IletinizClient(['apiKey' => getenv('ILETINIZ_API_KEY') ?: null]);
$data = $event['data'];
$muteFile = sys_get_temp_dir() . '/iletiniz-bot-mute.json';

/** @return array<string, int> */
$readMutes = static function () use ($muteFile): array {
    $raw = @file_get_contents($muteFile);
    $arr = $raw !== false ? json_decode($raw, true) : null;
    return is_array($arr) ? $arr : [];
};

switch ($event['event']) {
    case 'message.echo':
        // İşletme telefondan cevap verdi: bu kişi için botu sustur.
        $mutes = $readMutes();
        $mutes[(string) $data['to']] = time() + MUTE_SECONDS;
        file_put_contents($muteFile, json_encode($mutes));
        break;

    case 'message.received':
        $from = (string) $data['from'];
        $mutes = $readMutes();
        if (($mutes[$from] ?? 0) > time()) {
            break; // Temsilci ilgileniyor, bot cevap vermez.
        }

        // Dekont gibi belge/görsel geldiyse indir (Meta 7 gün saklar).
        if ($data['media'] !== null) {
            $client->whatsapp->saveMedia($data['wamid'], sys_get_temp_dir() . '/' . basename($data['media']['filename'] ?? $data['wamid']));
        }

        $choice = $data['interactive']['id'] ?? $data['button']['payload'] ?? null;
        $reply = match ($choice) {
            'aidat' => [
                'type' => 'cta_url',
                'body' => 'Kasım aidatınız: 1.500 TL. Son ödeme: 10 Aralık.',
                'displayText' => 'Ödeme yap',
                'url' => 'https://ornek-kulup.com/odeme',
            ],
            'antrenman' => [
                'type' => 'list',
                'body' => 'Hangi grubun saatlerini görmek istersiniz?',
                'buttonText' => 'Gruplar',
                'sections' => [['title' => 'Futbol', 'rows' => [
                    ['id' => 'u10', 'title' => 'U10'],
                    ['id' => 'u12', 'title' => 'U12'],
                ]]],
            ],
            'u10' => ['type' => 'text', 'text' => 'U10: Pazartesi ve Çarşamba 17:00.'],
            'u12' => ['type' => 'text', 'text' => 'U12: Salı ve Perşembe 17:00.'],
            'temsilci' => ['type' => 'text', 'text' => 'Bir temsilcimiz en kısa sürede size yazacak.'],
            default => [
                'type' => 'buttons',
                'body' => 'Merhaba, size nasıl yardımcı olabiliriz?',
                'buttons' => [
                    ['id' => 'aidat', 'title' => 'Aidat durumu'],
                    ['id' => 'antrenman', 'title' => 'Antrenman saatleri'],
                    ['id' => 'temsilci', 'title' => 'Temsilciye bağlan'],
                ],
            ],
        };

        $client->messages->send([
            'to' => $from,
            'provider' => 'whatsapp',
            'sender' => $data['phone_number_id'],
            'whatsappMessage' => $reply + ['replyTo' => $data['wamid']],
        ]);

        if ($choice === 'temsilci') {
            // Temsilci istendi: bot bu kişi için de susar.
            $mutes[$from] = time() + MUTE_SECONDS;
            file_put_contents($muteFile, json_encode($mutes));
        }
        break;
}

http_response_code(200);
