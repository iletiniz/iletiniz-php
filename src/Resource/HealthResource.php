<?php

declare(strict_types=1);

namespace Iletiniz\Resource;

use Iletiniz\Http\HttpClient;
use Iletiniz\RequestOptions;

final class HealthResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * API ve veritabanının erişilebilirliğini kontrol eder.
     *
     * @return array{ok: bool, db: 'up'|'down'}
     */
    public function check(?RequestOptions $options = null): array
    {
        /** @var array{ok: bool, db: 'up'|'down'} $res */
        $res = $this->http->request('GET', '/v1/health', [], null, $options);
        return $res;
    }
}
