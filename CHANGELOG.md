# Changelog / Değişiklik Günlüğü

Bu projedeki önemli değişiklikler bu dosyada belgelenir.
All notable changes to this project will be documented in this file.

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
Sürümleme / Versioning: [Semantic Versioning](https://semver.org/spec/v2.0.0.html)

## [Unreleased]

### Added
- `$client->partner->listApiKeys($id)` ve `$client->partner->revokeApiKey($id, $keyId)`: alt hesabın API anahtarlarını listeleme ve iptal etme.

## [0.2.0] - 2026-10-31

### Added
- `whatsappTemplate` (Meta onaylı şablon), bulk `items[].whatsappComponents`, WhatsApp için `sender` = `phone_number_id`.
- `scheduledAt` (dize veya DateTimeInterface), `fallback`, `idempotencyKey`, metin tabanlı `iys` modları.
- `$client->scheduled`, `$client->reminders`, `$client->partner` kaynakları.
- `tests/ClientTest.php` (sahte transport).

### Fixed
- `IletinizAPIError` PHP 8.2'de `Exception::$code` çakışması nedeniyle ölümcül hata veriyordu; özellik `apiCode` oldu, `getApiCode()` kullanın.
- API hata zarfı `{"error": {"code", "message"}}` artık çözülüyor.

### Changed
- Partner kota aktarımı / hesap ve anahtar oluşturma istekleri yeniden denenmez.

## [0.1.0] - 2026-05-12

### Eklenenler / Added
- Açık kaynak proje hijyen dosyaları: LICENSE (MIT), CONTRIBUTING, CODE_OF_CONDUCT, SECURITY
- İki dilli dokümantasyon (Türkçe + English)
- İlk açık kaynak yayını / Initial open-source release
