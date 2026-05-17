<?php

declare(strict_types=1);

namespace Iletiniz\Exception;

use Throwable;

class IletinizConnectionError extends IletinizError
{
    public function __construct(string $message, public readonly ?Throwable $cause = null)
    {
        parent::__construct($message, 0, $cause);
    }
}
