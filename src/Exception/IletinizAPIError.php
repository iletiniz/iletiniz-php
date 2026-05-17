<?php

declare(strict_types=1);

namespace Iletiniz\Exception;

class IletinizAPIError extends IletinizError
{
    /**
     * @param array<string, mixed>|string|null $body
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $code = null,
        public readonly array|string|null $body = null,
        public readonly ?string $requestId = null,
    ) {
        parent::__construct($message);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getCode(): string
    {
        return $this->code ?? '';
    }

    public function getApiCode(): ?string
    {
        return $this->code;
    }

    /**
     * @return array<string, mixed>|string|null
     */
    public function getBody(): array|string|null
    {
        return $this->body;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}
