<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Iletiniz\IletinizClient;

$client = new IletinizClient([
    'apiKey' => getenv('ILETINIZ_API_KEY') ?: null,
]);

// Oturum mesajı: yalnız son 24 saat içinde size yazmış kişiye gönderilebilir.
// Pencere dışındaysa API `reengagement_window_expired` döner; o durumda onaylı şablon kullanın.
$result = $client->messages->send([
    'to' => '+905551234567',
    'provider' => 'whatsapp',
    'whatsappMessage' => [
        'type' => 'buttons',
        'header' => ['type' => 'text', 'text' => 'Örnek Mağaza'],
        'body' => 'Merhaba, size nasıl yardımcı olabiliriz?',
        'footer' => 'Bir seçenek seçin',
        'buttons' => [
            ['id' => 'siparis', 'title' => 'Sipariş durumu'],
            ['id' => 'yardim', 'title' => 'Yardım konuları'],
            ['id' => 'temsilci', 'title' => 'Temsilciye bağlan'],
        ],
    ],
]);

echo 'Buton mesajı: ' . $result['status'] . ' (' . $result['job_id'] . ')' . PHP_EOL;

// Liste mesajı: en fazla 10 bölüm, toplam en fazla 10 satır.
$client->messages->send([
    'to' => '+905551234567',
    'provider' => 'whatsapp',
    'whatsappMessage' => [
        'type' => 'list',
        'body' => 'Hangi konuda yardım istersiniz?',
        'buttonText' => 'Konular',
        'sections' => [
            ['title' => 'Siparişler', 'rows' => [
                ['id' => 'kargo', 'title' => 'Kargo takibi', 'description' => 'Siparişiniz nerede?'],
                ['id' => 'iade', 'title' => 'İade talebi', 'description' => '14 gün içinde ücretsiz iade'],
            ]],
            ['title' => 'Hesabım', 'rows' => [
                ['id' => 'fatura', 'title' => 'Faturalarım', 'description' => 'Son 12 ayın faturaları'],
            ]],
        ],
    ],
]);

// Bağlantı butonu (ör. kargo takip ya da ödeme sayfası).
$client->messages->send([
    'to' => '+905551234567',
    'provider' => 'whatsapp',
    'whatsappMessage' => [
        'type' => 'cta_url',
        'body' => 'Siparişiniz (#10482) kargoya verildi.',
        'displayText' => 'Kargoyu takip et',
        'url' => 'https://ornek.com/kargo/10482',
    ],
]);
