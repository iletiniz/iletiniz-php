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
        'header' => ['type' => 'text', 'text' => 'Blue Academy'],
        'body' => 'Merhaba, size nasıl yardımcı olabiliriz?',
        'footer' => 'Bir seçenek seçin',
        'buttons' => [
            ['id' => 'aidat', 'title' => 'Aidat durumu'],
            ['id' => 'antrenman', 'title' => 'Antrenman saatleri'],
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
        'body' => 'Antrenman grubunuzu seçin.',
        'buttonText' => 'Gruplar',
        'sections' => [
            ['title' => 'Futbol', 'rows' => [
                ['id' => 'u10', 'title' => 'U10', 'description' => 'Pazartesi, Çarşamba 17:00'],
                ['id' => 'u12', 'title' => 'U12', 'description' => 'Salı, Perşembe 17:00'],
            ]],
            ['title' => 'Basketbol', 'rows' => [
                ['id' => 'b12', 'title' => 'B12', 'description' => 'Cumartesi 10:00'],
            ]],
        ],
    ],
]);

// Bağlantı butonu (ör. ödeme sayfası).
$client->messages->send([
    'to' => '+905551234567',
    'provider' => 'whatsapp',
    'whatsappMessage' => [
        'type' => 'cta_url',
        'body' => 'Kasım aidatınız: 1.500 TL',
        'displayText' => 'Ödeme yap',
        'url' => 'https://ornek-kulup.com/odeme/123',
    ],
]);
