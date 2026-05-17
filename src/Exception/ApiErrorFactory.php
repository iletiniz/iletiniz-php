<?php

declare(strict_types=1);

namespace Iletiniz\Exception;

final class ApiErrorFactory
{
    /**
     * @param array<string, mixed>|string|null $body
     */
    public static function build(int $status, array|string|null $body, ?string $requestId): IletinizAPIError
    {
        $code = null;
        $message = null;

        if (is_array($body)) {
            if (isset($body['error']) && is_string($body['error'])) {
                $code = $body['error'];
            }
            if (isset($body['message']) && is_string($body['message'])) {
                $message = $body['message'];
            }
        } elseif (is_string($body) && $body !== '') {
            $message = $body;
        }

        if ($message === null || $message === '') {
            $message = "HTTP {$status}";
        }

        return match (true) {
            $status === 401 => new IletinizAuthenticationError($message, $status, $code, $body, $requestId),
            $status === 403 => new IletinizPermissionError($message, $status, $code, $body, $requestId),
            $status === 404 => new IletinizNotFoundError($message, $status, $code, $body, $requestId),
            $status === 400 || $status === 422 => new IletinizValidationError($message, $status, $code, $body, $requestId),
            $status === 429 => new IletinizRateLimitError($message, $status, $code, $body, $requestId),
            $status >= 500 => new IletinizServerError($message, $status, $code, $body, $requestId),
            default => new IletinizAPIError($message, $status, $code, $body, $requestId),
        };
    }
}
