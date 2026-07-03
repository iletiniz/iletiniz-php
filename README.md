# İletiniz PHP SDK

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](./LICENSE)

Iletiniz API için resmi PHP SDK'si. PHP 8.1+ üzerinde çalışır.

## Kurulum

```bash
composer require iletiniz/sdk
```

Gereksinimler:

- PHP `>= 8.1`
- `ext-json`, `ext-curl`

## Hızlı başlangıç

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Iletiniz\IletinizClient;

$client = new IletinizClient([
    'apiKey' => getenv('ILETINIZ_API_KEY') ?: null, // 'iltz_live_…' veya 'iltz_test_…'
]);

$result = $client->messages->send([
    'to' => '+905551234567',
    'body' => 'Merhaba!',
]);

echo $result['job_id'], ' ', $result['status'];
```

`apiKey` verilmediğinde SDK `ILETINIZ_API_KEY` ortam değişkenini okur.

## Yapılandırma

```php
new IletinizClient([
    'apiKey' => 'iltz_live_…',
    'baseUrl' => 'https://api.iletiniz.com', // varsayılan
    'timeoutMs' => 30000,                     // varsayılan
    'maxRetries' => 2,                        // 408/429/5xx ve ağ hatalarında
    'defaultHeaders' => ['X-Source' => 'crm'],
    'transport' => null,                      // özel TransportInterface implementasyonu
]);
```

## Endpoint'ler

SDK, public API yüzeyini kapsar:

| Metot                                            | HTTP                              |
| ------------------------------------------------ | --------------------------------- |
| `$client->health->check()`                       | `GET /v1/health`                  |
| `$client->messages->send($params)`               | `POST /v1/messages`               |
| `$client->messages->sendBulk($params)`           | `POST /v1/messages/bulk`          |
| `$client->messages->retrieve($jobId)`            | `GET /v1/messages/{jobId}`        |
| `$client->messages->status($jobId)` (alias)      | `GET /v1/messages/{jobId}`        |

### Tek mesaj göndermek

```php
$client->messages->send([
    'to' => '+905551234567',
    'body' => 'Sipariş kodunuz: 4821',
    'sender' => 'MAGAZA',     // opsiyonel
    'provider' => 'netgsm',   // opsiyonel
]);
```

### Telegram üzerinden göndermek

`'provider' => 'telegram'` seçildiğinde `to` alanı SMS yerine Telegram alıcı tanımlayıcısı bekler:
numerik `chat_id` (örn `8409353994`, gruplar için `-1001234567890`) veya `@kullaniciadi`. `sender` Telegram için kullanılmaz — bot kimliği bağlantıdaki token'a gömülüdür.

```php
$client->messages->send([
    'to' => '8409353994',
    'body' => 'Merhaba!',
    'provider' => 'telegram',
]);
```

### Sağlayıcılar-arası fallback

Birincil sağlayıcı mesajı **reddederse** (hard-fail: sağlayıcı hata döner veya bağlantı kurulamaz), aynı mesaj (aynı alıcı, aynı içerik, aynı SMS kanalı) sıradaki yedek sağlayıcıyla otomatik yeniden denenir. İlk **başarıda** durur. `fallback` en fazla 3 sağlayıcı kodundan oluşan sıralı bir dizidir; hepsi müşterinin bağlı `kind: sms` sağlayıcıları olmalı ve ne birincil ile ne de birbirleriyle aynı olabilir.

```php
$result = $client->messages->send([
    'to' => '+905551234567',
    'body' => 'Sipariş kodunuz: 4821',
    'provider' => 'netgsm',                        // birincil
    'fallback' => ['verimor', 'iletimerkezi'],     // sıralı yedekler (max 3)
]);

// $result['provider']  → mesajı KABUL eden sağlayıcı
// $result['attempts']  → denenen her sağlayıcı + sonucu (opsiyonel)
```

> **Kota tek sayım:** Bir mantıksal mesaj, kaç sağlayıcı denenirse denensin **tek** kota harcar; hepsi başarısız olursa hiç kota harcanmaz.
>
> **Kapsam:** Yalnızca **reddte (hard-fail)** tetiklenir ve yalnızca **SMS→SMS**'tir (kanallar arası değil, örn. WhatsApp→SMS yok). "Teslim edilemedi / timeout" için otomatik fallback henüz yoktur (gelecek sürüm).

`sendBulk` yanıtında kabul eden sağlayıcı, öğe bazında `delivered_via` alanında döner.

### Template ile göndermek

```php
$client->messages->send([
    'to' => '+905551234567',
    'template' => 'order_shipped',
    'variables' => ['name' => 'Ayşe', 'tracking_no' => 'TR123'],
]);
```

`body` ve `template` aynı anda kullanılamaz; tam olarak biri zorunludur. `variables` yalnızca `template` ile birlikte verilebilir.

### Toplu gönderim

Tek istekte en fazla 200 öğe gönderebilirsiniz.

```php
// Düz metin modu — her item'da body zorunlu, variables yok
$client->messages->sendBulk([
    'items' => [
        ['to' => '+905551111111', 'body' => 'Mesaj 1'],
        ['to' => '+905552222222', 'body' => 'Mesaj 2'],
    ],
]);

// Template modu — items'ta body olmamalı
$client->messages->sendBulk([
    'template' => 'low_stock_alert',
    'items' => [
        ['to' => '+905551111111', 'variables' => ['product' => 'Ürün A', 'stock' => 3]],
        ['to' => '+905552222222', 'variables' => ['product' => 'Ürün B', 'stock' => 1]],
    ],
]);
```

### Mesaj durumunu sorgulamak

```php
$info = $client->messages->retrieve($jobId);
// $info['status']: 'sent' | 'queued' | 'failed' | 'delivered' | 'expired' | 'rejected' | 'unknown'
```

### Sağlık kontrolü

```php
$health = $client->health->check();
// ['ok' => true, 'db' => 'up']
```

## Hata yönetimi

Tüm hatalar `Iletiniz\Exception\IletinizError` sınıfından türetilir. HTTP status'a göre uygun alt sınıf fırlatılır:

```php
use Iletiniz\Exception\IletinizAPIError;
use Iletiniz\Exception\IletinizAuthenticationError;
use Iletiniz\Exception\IletinizConnectionError;
use Iletiniz\Exception\IletinizNotFoundError;
use Iletiniz\Exception\IletinizRateLimitError;
use Iletiniz\Exception\IletinizServerError;
use Iletiniz\Exception\IletinizTimeoutError;
use Iletiniz\Exception\IletinizValidationError;

try {
    $client->messages->send(['to' => '+905551234567', 'body' => 'test']);
} catch (IletinizAuthenticationError $e) {
    // 401 — geçersiz veya iptal edilmiş anahtar
} catch (IletinizValidationError $e) {
    // 400 / 422 — istek doğrulanamadı
    var_dump($e->getBody());
} catch (IletinizRateLimitError $e) {
    // 429 — yeniden denemeden önce backoff
} catch (IletinizNotFoundError $e) {
    // 404
} catch (IletinizServerError $e) {
    // 5xx
} catch (IletinizAPIError $e) {
    error_log(sprintf('%d %s %s [%s]', $e->status, $e->getApiCode() ?? '-', $e->getMessage(), $e->getRequestId() ?? '-'));
} catch (IletinizTimeoutError $e) {
    // istek timeout'a takıldı
} catch (IletinizConnectionError $e) {
    // ağ hatası
}
```

## Yeniden deneme stratejisi

SDK, aşağıdaki durumlarda otomatik olarak `maxRetries` defa yeniden dener (varsayılan: 2):

- Ağ kaynaklı bağlantı hataları
- HTTP 408, 429, 500–599

`Retry-After` başlığı varsa beklenir; aksi halde exponential backoff (jitter ile) uygulanır. Yeniden denemeyi kapatmak için `maxRetries: 0` verin.

## Timeout

Her istek için ayrıca timeout verebilirsiniz:

```php
use Iletiniz\RequestOptions;

$client->messages->send(
    ['to' => '+905551234567', 'body' => 'merhaba'],
    new RequestOptions(timeoutMs: 10000),
);
```

## Test

SDK, `Iletiniz\Http\TransportInterface` üzerinden HTTP katmanını dışarı açar. Testlerinizde gerçek ağ trafiği oluşturmadan SDK'yı kullanabilirsiniz:

```php
use Iletiniz\Http\HttpResponse;
use Iletiniz\Http\TransportInterface;
use Iletiniz\IletinizClient;

$transport = new class implements TransportInterface {
    public function send(string $method, string $url, array $headers, ?string $body, int $timeoutMs): HttpResponse
    {
        return new HttpResponse(200, '{"ok":true,"db":"up"}', []);
    }
};

$client = new IletinizClient([
    'apiKey' => 'iltz_test_xxx',
    'transport' => $transport,
]);
```

## Katkıda Bulunma / Contributing

Katkı sağlamak ister misiniz? Lütfen [CONTRIBUTING.md](./CONTRIBUTING.md) dosyasını inceleyin. English: [CONTRIBUTING.en.md](./CONTRIBUTING.en.md).

## Davranış Kuralları / Code of Conduct

Bu proje [Contributor Covenant](./CODE_OF_CONDUCT.md) davranış kurallarına bağlıdır. English: [CODE_OF_CONDUCT.en.md](./CODE_OF_CONDUCT.en.md).

## Güvenlik / Security

Güvenlik açığı bildirmek için lütfen [SECURITY.md](./SECURITY.md) dosyasındaki adımları izleyin — **public issue açmayın**. English: [SECURITY.en.md](./SECURITY.en.md).

## Lisans / License

MIT — bkz. / see [LICENSE](./LICENSE).
