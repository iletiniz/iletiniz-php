<?php

declare(strict_types=1);

namespace Iletiniz;

use Iletiniz\Exception\IletinizError;
use Iletiniz\Http\CurlTransport;
use Iletiniz\Http\HttpClient;
use Iletiniz\Http\TransportInterface;
use Iletiniz\Resource\HealthResource;
use Iletiniz\Resource\MessagesResource;

final class IletinizClient
{
    public const VERSION = '0.1.0';

    private const DEFAULT_BASE_URL = 'https://api.iletiniz.com';
    private const DEFAULT_TIMEOUT_MS = 30000;
    private const DEFAULT_MAX_RETRIES = 2;
    private const API_KEY_REGEX = '/^iltz_(?:live|test)_[A-Za-z0-9_-]+$/';

    public readonly MessagesResource $messages;
    public readonly HealthResource $health;

    /**
     * @param array{
     *     apiKey?: string|null,
     *     baseUrl?: string|null,
     *     timeoutMs?: int|null,
     *     maxRetries?: int|null,
     *     defaultHeaders?: array<string, string>|null,
     *     transport?: TransportInterface|null,
     * } $options
     */
    public function __construct(array $options = [])
    {
        $apiKey = $options['apiKey'] ?? self::readEnv('ILETINIZ_API_KEY');
        if (!is_string($apiKey) || $apiKey === '') {
            throw new IletinizError(
                'API anahtarı gerekli. `new IletinizClient(["apiKey" => ...])` veya ILETINIZ_API_KEY ortam değişkeni kullanın.',
            );
        }
        if (preg_match(self::API_KEY_REGEX, $apiKey) !== 1) {
            throw new IletinizError(
                "Geçersiz API anahtar formatı. Beklenen: 'iltz_live_…' veya 'iltz_test_…'.",
            );
        }

        $baseUrl = $options['baseUrl'] ?? self::readEnv('ILETINIZ_BASE_URL') ?? self::DEFAULT_BASE_URL;
        $timeoutMs = $options['timeoutMs'] ?? self::DEFAULT_TIMEOUT_MS;
        $maxRetries = $options['maxRetries'] ?? self::DEFAULT_MAX_RETRIES;

        $defaultHeaders = array_merge(
            ['User-Agent' => 'iletiniz-php/' . self::VERSION],
            $options['defaultHeaders'] ?? [],
        );

        $transport = $options['transport'] ?? new CurlTransport();

        $http = new HttpClient(
            baseUrl: $baseUrl,
            apiKey: $apiKey,
            timeoutMs: $timeoutMs,
            maxRetries: $maxRetries,
            defaultHeaders: $defaultHeaders,
            transport: $transport,
        );

        $this->messages = new MessagesResource($http);
        $this->health = new HealthResource($http);
    }

    private static function readEnv(string $name): ?string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            return null;
        }
        return $value;
    }
}
