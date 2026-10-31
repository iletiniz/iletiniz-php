<?php

declare(strict_types=1);

namespace Iletiniz\Resource;

use Iletiniz\Exception\IletinizError;
use Iletiniz\Http\HttpClient;
use Iletiniz\RequestOptions;

/**
 * Partner API (`/v1/partner`). Yalnız partner hesabının `partner:manage` yetkili
 * CANLI (iltz_live_) anahtarıyla çalışır.
 *
 * Alt hesap biçimi:
 * array{id: int, name: string, slug: string, status: 'active'|'suspended',
 *       credit_balance: int, created_at: string, suspended_at: string|null}
 */
final class PartnerResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * @return array{workspace_id: int, name: string, pricing: array{discount_pct: float|null, package: string|null}, credit_balance: int, child_count: int}
     */
    public function profile(?RequestOptions $options = null): array
    {
        /** @var array{workspace_id: int, name: string, pricing: array{discount_pct: float|null, package: string|null}, credit_balance: int, child_count: int} $res */
        $res = $this->http->request('GET', '/v1/partner', [], null, $options);
        return $res;
    }

    /**
     * @param array{name: string, externalRef?: string} $params
     * @return array<string, mixed>
     */
    public function createAccount(array $params, ?RequestOptions $options = null): array
    {
        $name = $params['name'] ?? '';
        if (mb_strlen(trim($name)) < 2) {
            throw new IletinizError("'name' en az 2 karakter olmalıdır.");
        }
        /** @var array<string, mixed> $res */
        $res = $this->http->request('POST', '/v1/partner/accounts', [], Util::prepare($params), $options, retry: false);
        return $res;
    }

    /**
     * @param array{status?: 'active'|'suspended', limit?: int, offset?: int} $params
     * @return array{data: list<array<string, mixed>>, limit: int, offset: int}
     */
    public function listAccounts(array $params = [], ?RequestOptions $options = null): array
    {
        /** @var array{data: list<array<string, mixed>>, limit: int, offset: int} $res */
        $res = $this->http->request('GET', '/v1/partner/accounts', Util::listQuery($params), null, $options);
        return $res;
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveAccount(int $id, ?RequestOptions $options = null): array
    {
        Util::assertId($id);
        /** @var array<string, mixed> $res */
        $res = $this->http->request('GET', '/v1/partner/accounts/' . $id, [], null, $options);
        return $res;
    }

    /**
     * @return array<string, mixed>
     */
    public function suspendAccount(int $id, ?RequestOptions $options = null): array
    {
        Util::assertId($id);
        /** @var array<string, mixed> $res */
        $res = $this->http->request('POST', '/v1/partner/accounts/' . $id . '/suspend', [], null, $options);
        return $res;
    }

    /**
     * @return array<string, mixed>
     */
    public function unsuspendAccount(int $id, ?RequestOptions $options = null): array
    {
        Util::assertId($id);
        /** @var array<string, mixed> $res */
        $res = $this->http->request('POST', '/v1/partner/accounts/' . $id . '/unsuspend', [], null, $options);
        return $res;
    }

    /**
     * Alt hesap için API anahtarı üretir. Düz metin `key` yalnız bu yanıtta döner.
     *
     * @param array{name: string, scopes?: list<string>, test?: bool, allowedIps?: list<string>} $params
     * @return array{id: int, workspace_id: int, name: string, key: string, key_prefix: string, scopes: list<string>, sandbox: bool}
     */
    public function createApiKey(int $id, array $params, ?RequestOptions $options = null): array
    {
        Util::assertId($id);
        if (($params['name'] ?? '') === '') {
            throw new IletinizError("'name' zorunludur.");
        }
        /** @var array{id: int, workspace_id: int, name: string, key: string, key_prefix: string, scopes: list<string>, sandbox: bool} $res */
        $res = $this->http->request('POST', '/v1/partner/accounts/' . $id . '/api-keys', [], Util::prepare($params), $options, retry: false);
        return $res;
    }

    /**
     * Alt hesabın API anahtarlarını listeler (en yeni önce). Düz metin anahtar/hash dönmez.
     *
     * @return array{data: list<array{id: int, name: string, key_prefix: string, scopes: list<string>|null, sandbox: bool, source: string, status: 'active'|'revoked', created_at: string, last_used_at: string|null, revoked_at: string|null}>}
     */
    public function listApiKeys(int $id, ?RequestOptions $options = null): array
    {
        Util::assertId($id);
        /** @var array{data: list<array{id: int, name: string, key_prefix: string, scopes: list<string>|null, sandbox: bool, source: string, status: 'active'|'revoked', created_at: string, last_used_at: string|null, revoked_at: string|null}>} $res */
        $res = $this->http->request('GET', '/v1/partner/accounts/' . $id . '/api-keys', [], null, $options);
        return $res;
    }

    /**
     * Alt hesabın API anahtarını iptal eder. İdempotenttir: iptal edilmiş anahtar için aynı nesne döner.
     *
     * @return array{id: int, name: string, key_prefix: string, scopes: list<string>|null, sandbox: bool, source: string, status: 'active'|'revoked', created_at: string, last_used_at: string|null, revoked_at: string|null}
     */
    public function revokeApiKey(int $id, int $keyId, ?RequestOptions $options = null): array
    {
        Util::assertId($id);
        Util::assertId($keyId, 'keyId');
        /** @var array{id: int, name: string, key_prefix: string, scopes: list<string>|null, sandbox: bool, source: string, status: 'active'|'revoked', created_at: string, last_used_at: string|null, revoked_at: string|null} $res */
        $res = $this->http->request('DELETE', '/v1/partner/accounts/' . $id . '/api-keys/' . $keyId, [], null, $options);
        return $res;
    }

    /**
     * Partner kotasından alt hesaba mesaj kotası aktarır. Yeniden denenmez (çift aktarım riski).
     *
     * @return array{child_workspace_id: int, amount: int, partner_balance: int, child_balance: int}
     */
    public function transferQuota(int $id, int $amount, ?RequestOptions $options = null): array
    {
        Util::assertId($id);
        if ($amount < 1 || $amount > 10000000) {
            throw new IletinizError("'amount' 1 ile 10.000.000 arasında olmalıdır.");
        }
        /** @var array{child_workspace_id: int, amount: int, partner_balance: int, child_balance: int} $res */
        $res = $this->http->request('POST', '/v1/partner/accounts/' . $id . '/quota-transfers', [], ['amount' => $amount], $options, retry: false);
        return $res;
    }

    /**
     * Alt hesabın WhatsApp numarasını bağlaması için tek kullanımlık bağlantı (24 saat geçerli).
     *
     * @return array{child_workspace_id: int, url: string, expires_at: string}
     */
    public function createWhatsappSignupLink(int $id, ?RequestOptions $options = null): array
    {
        Util::assertId($id);
        /** @var array{child_workspace_id: int, url: string, expires_at: string} $res */
        $res = $this->http->request('POST', '/v1/partner/accounts/' . $id . '/whatsapp-signup', [], null, $options);
        return $res;
    }
}
