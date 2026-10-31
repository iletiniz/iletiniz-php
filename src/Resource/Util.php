<?php

declare(strict_types=1);

namespace Iletiniz\Resource;

use Iletiniz\Exception\IletinizError;

/**
 * @internal
 */
final class Util
{
    /**
     * @param array<string, mixed> $arr
     * @return array<string, mixed>
     */
    public static function stripNulls(array $arr): array
    {
        return array_filter($arr, static fn (mixed $v): bool => $v !== null);
    }

    /**
     * Null alanları atar, üst seviyedeki DateTimeInterface değerlerini ISO 8601 dizesine çevirir.
     *
     * @param array<string, mixed> $arr
     * @return array<string, mixed>
     */
    public static function prepare(array $arr): array
    {
        $out = [];
        foreach (self::stripNulls($arr) as $k => $v) {
            $out[$k] = $v instanceof \DateTimeInterface ? $v->format(\DateTimeInterface::RFC3339) : $v;
        }
        return $out;
    }

    public static function assertId(int $id, string $label = 'id'): void
    {
        if ($id <= 0) {
            throw new IletinizError(sprintf('%s pozitif tam sayı olmalıdır.', $label));
        }
    }

    /**
     * @param array{status?: string, limit?: int, offset?: int} $params
     * @return array<string, string|int|float|bool|null>
     */
    public static function listQuery(array $params): array
    {
        return [
            'status' => $params['status'] ?? null,
            'limit' => $params['limit'] ?? null,
            'offset' => $params['offset'] ?? null,
        ];
    }
}
