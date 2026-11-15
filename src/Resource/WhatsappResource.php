<?php

declare(strict_types=1);

namespace Iletiniz\Resource;

use Iletiniz\Exception\IletinizError;
use Iletiniz\Http\HttpClient;
use Iletiniz\RequestOptions;

/**
 * WhatsApp'a özgü uçlar: gelen mesajın medyasını indirme.
 */
final class WhatsappResource
{
    private const WAMID_REGEX = '/^[A-Za-z0-9._=+\/-]{1,191}$/';

    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * Gelen (`message.received`) ya da telefondan giden (`message.echo`) WhatsApp
     * mesajının medyasını indirir. `$wamid` olaydaki `wamid` alanıdır.
     *
     * Meta gelen medyayı 7 gün saklar; sonrasında `IletinizAPIError` (410, `media_expired`).
     * Test (`iltz_test_`) anahtarıyla kullanılamaz (403, `sandbox_not_allowed`).
     *
     * @return array{content: string, contentType: string, filename: ?string}
     */
    public function downloadMedia(string $wamid, ?RequestOptions $options = null): array
    {
        if (preg_match(self::WAMID_REGEX, $wamid) !== 1) {
            throw new IletinizError("Geçersiz 'wamid'.");
        }
        $res = $this->http->requestRaw(
            'GET',
            '/v1/whatsapp/messages/' . rawurlencode($wamid) . '/media',
            [],
            $options,
        );
        return [
            'content' => $res->body,
            'contentType' => $res->getHeader('content-type') ?? 'application/octet-stream',
            'filename' => self::filenameFrom($res->getHeader('content-disposition')),
        ];
    }

    /**
     * Medyayı indirip `$path`'e yazar. Yazılan bayt sayısını döner.
     */
    public function saveMedia(string $wamid, string $path, ?RequestOptions $options = null): int
    {
        $media = $this->downloadMedia($wamid, $options);
        $written = file_put_contents($path, $media['content']);
        if ($written === false) {
            throw new IletinizError(sprintf('Medya dosyaya yazılamadı: %s', $path));
        }
        return $written;
    }

    /**
     * `Content-Disposition` başlığından dosya adı (önce RFC 5987 `filename*`).
     *
     * @internal
     */
    public static function filenameFrom(?string $disposition): ?string
    {
        if ($disposition === null || $disposition === '') {
            return null;
        }
        if (preg_match("/filename\\*=UTF-8''([^;]+)/i", $disposition, $m) === 1) {
            return rawurldecode(trim($m[1]));
        }
        if (preg_match('/filename="([^"]*)"/i', $disposition, $m) === 1) {
            return $m[1];
        }
        return null;
    }
}
