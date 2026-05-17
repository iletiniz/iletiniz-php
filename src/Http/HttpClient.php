<?php

declare(strict_types=1);

namespace Iletiniz\Http;

use Iletiniz\Exception\ApiErrorFactory;
use Iletiniz\Exception\IletinizConnectionError;
use Iletiniz\Exception\IletinizTimeoutError;
use Iletiniz\RequestOptions;

class HttpClient
{
    private const RETRYABLE_STATUSES = [408, 429];

    /**
     * @param array<string, string> $defaultHeaders
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeoutMs,
        private readonly int $maxRetries,
        private readonly array $defaultHeaders,
        private readonly TransportInterface $transport,
    ) {
    }

    /**
     * @param 'GET'|'POST'|'PUT'|'DELETE'|'PATCH' $method
     * @param array<string, string|int|float|bool|null> $query
     * @param mixed $body
     * @return mixed
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        mixed $body = null,
        ?RequestOptions $options = null,
    ): mixed {
        $url = $this->buildUrl($path, $query);

        $headers = array_merge(
            $this->defaultHeaders,
            [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ],
            $options?->headers ?? [],
        );

        $payload = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                throw new IletinizConnectionError('İstek gövdesi JSON olarak kodlanamadı.');
            }
        }

        $timeoutMs = $options?->timeoutMs ?? $this->timeoutMs;

        $attempt = 0;
        while (true) {
            try {
                $response = $this->transport->send($method, $url, $headers, $payload, $timeoutMs);
            } catch (IletinizTimeoutError $e) {
                if ($this->shouldRetry(null, $attempt)) {
                    $attempt++;
                    $this->sleep($this->backoffMs($attempt, null));
                    continue;
                }
                throw $e;
            } catch (IletinizConnectionError $e) {
                if ($this->shouldRetry(null, $attempt)) {
                    $attempt++;
                    $this->sleep($this->backoffMs($attempt, null));
                    continue;
                }
                throw $e;
            }

            $status = $response->status;
            if ($status >= 200 && $status < 300) {
                if ($status === 204 || $response->body === '') {
                    return null;
                }
                $decoded = json_decode($response->body, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new IletinizConnectionError('Sunucudan geçersiz JSON döndü.');
                }
                return $decoded;
            }

            if ($this->shouldRetry($status, $attempt)) {
                $attempt++;
                $this->sleep($this->backoffMs($attempt, $response->getHeader('retry-after')));
                continue;
            }

            $requestId = $response->getHeader('x-request-id');
            $errorBody = $this->parseErrorBody($response->body);
            throw ApiErrorFactory::build($status, $errorBody, $requestId);
        }
    }

    /**
     * @param array<string, string|int|float|bool|null> $query
     */
    private function buildUrl(string $path, array $query): string
    {
        $base = rtrim($this->baseUrl, '/');
        $p = str_starts_with($path, '/') ? $path : '/' . $path;
        $url = $base . $p;

        $params = [];
        foreach ($query as $k => $v) {
            if ($v === null) {
                continue;
            }
            if (is_bool($v)) {
                $params[$k] = $v ? 'true' : 'false';
                continue;
            }
            $params[$k] = (string) $v;
        }
        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }

    private function shouldRetry(?int $status, int $attempt): bool
    {
        if ($attempt >= $this->maxRetries) {
            return false;
        }
        if ($status === null) {
            return true;
        }
        if (in_array($status, self::RETRYABLE_STATUSES, true)) {
            return true;
        }
        return $status >= 500 && $status <= 599;
    }

    private function backoffMs(int $attempt, ?string $retryAfter): int
    {
        if ($retryAfter !== null && $retryAfter !== '') {
            $sec = (float) $retryAfter;
            if ($sec > 0 && is_finite($sec)) {
                return (int) min($sec * 1000.0, 30000.0);
            }
        }
        $base = (int) min((2 ** $attempt) * 250, 4000);
        return $base + random_int(0, 100);
    }

    private function sleep(int $ms): void
    {
        usleep($ms * 1000);
    }

    /**
     * @return array<string, mixed>|string|null
     */
    private function parseErrorBody(string $body): array|string|null
    {
        if ($body === '') {
            return null;
        }
        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            /** @var array<string, mixed> $decoded */
            return $decoded;
        }
        return $body;
    }
}
