<?php

namespace VendWeave\Gateway\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Certification Manager for VendWeave Badge System.
 * 
 * Phase-8: Manages integration certification status, badge qualification,
 * and verification with VendWeave Authority API.
 * 
 * All methods degrade safely when:
 * - Certification is disabled
 * - Authority API is unavailable
 * - Cache is unavailable
 */
class CertificationManager
{
    public const BADGE_BASE = 'VW-CERT-BASE';
    public const BADGE_REF = 'VW-CERT-REF';
    public const BADGE_GOV = 'VW-CERT-GOV';
    public const BADGE_FIN = 'VW-CERT-FIN';
    public const BADGE_CUR = 'VW-CERT-CUR';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    private const CACHE_KEY = 'vendweave:certification:status';
    private const REVOCATION_CACHE_KEY = 'vendweave:certification:revoked';

    /**
     * Check if certification system is enabled.
     */
    public static function isEnabled(): bool
    {
        return (bool) config('vendweave.certification.enabled', false);
    }

    /**
     * Get current certification status from cache or API.
     * 
     * @return array|null Certification status or null if unavailable
     */
    public static function status(): ?array
    {
        if (!self::isEnabled()) {
            return null;
        }

        // Check revocation first (always overrides cache)
        if (self::isRevoked()) {
            return [
                'status' => self::STATUS_REVOKED,
                'badge_code' => null,
                'message' => 'Certification has been revoked',
            ];
        }

        // Check cache
        $cached = self::getCachedStatus();
        if ($cached !== null) {
            return $cached;
        }

        // Fetch from API
        return self::fetchStatusFromApi();
    }

    /**
     * Detect which badge the current integration qualifies for.
     * 
     * Based on enabled features and config flags.
     * 
     * @return string Badge code
     */
    public static function detectQualifiedBadge(): string
    {
        if (self::qualifiesForCurrency()) {
            return self::BADGE_CUR;
        }
        if (self::qualifiesForFinancial()) {
            return self::BADGE_FIN;
        }
        if (self::qualifiesForGovernance()) {
            return self::BADGE_GOV;
        }
        if (self::qualifiesForReference()) {
            return self::BADGE_REF;
        }
        return self::BADGE_BASE;
    }

    /**
     * Get feature snapshot for certification request.
     * 
     * @return array Feature detection results
     */
    public static function getFeatureSnapshot(): array
    {
        return [
            'sdk_version' => self::getSdkVersion(),
            'qualified_badge' => self::detectQualifiedBadge(),
            'features' => [
                'base' => self::qualifiesForBase(),
                'reference_strict' => self::qualifiesForReference(),
                'governance' => self::qualifiesForGovernance(),
                'financial' => self::qualifiesForFinancial(),
                'currency' => self::qualifiesForCurrency(),
            ],
            'services' => [
                'transaction_verifier' => class_exists(TransactionVerifier::class),
                'reference_governor' => class_exists(ReferenceGovernor::class) && ReferenceGovernor::isAvailable(),
                'financial_record_manager' => class_exists(FinancialRecordManager::class) && FinancialRecordManager::isAvailable(),
                'currency_normalizer' => class_exists(CurrencyNormalizer::class),
            ],
            'config' => [
                'api_key_set' => !empty(config('vendweave.api_key')),
                'store_slug_set' => !empty(config('vendweave.store_slug')),
                'reference_strict_mode' => (bool) config('vendweave.reference_strict_mode', false),
                'reference_governance_enabled' => (bool) config('vendweave.reference_governance.enabled', false),
                'financial_reconciliation_enabled' => (bool) config('vendweave.financial_reconciliation.enabled', false),
                'base_currency_set' => !empty(config('vendweave.base_currency')),
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Request certification from VendWeave Authority.
     * 
     * @param string $domain Domain or App ID to certify
     * @param string $projectName Human-readable project name
     * @return array|null Certification response or null on failure
     */
    public static function requestCertification(string $domain, string $projectName): ?array
    {
        if (!self::isEnabled()) {
            return null;
        }

        try {
            $endpoint = self::getAuthorityEndpoint('/certify/request');
            $payload = [
                'domain' => $domain,
                'project_name' => $projectName,
                'store_slug' => config('vendweave.store_slug'),
                'snapshot' => self::getFeatureSnapshot(),
            ];

            $response = Http::timeout(30)
                ->withHeaders(self::getAuthHeaders())
                ->post($endpoint, $payload);

            if ($response->successful()) {
                $data = $response->json();
                self::cacheStatus($data);
                self::log('info', '[VendWeave] Certification requested', [
                    'domain' => $domain,
                    'project_name' => $projectName,
                    'badge_code' => $data['badge_code'] ?? null,
                    'status' => $data['status'] ?? null,
                ]);
                return $data;
            }

            self::log('warning', '[VendWeave] Certification request failed', [
                'domain' => $domain,
                'http_status' => $response->status(),
                'response' => $response->json(),
            ]);

            return null;
        } catch (Throwable $e) {
            self::log('error', '[VendWeave] Certification request error', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Verify a badge hash with VendWeave Authority.
     * 
     * @param string $hash Verification hash
     * @return array|null Verification result or null on failure
     */
    public static function verifyBadge(string $hash): ?array
    {
        try {
            $endpoint = self::getAuthorityEndpoint("/certify/verify/{$hash}");

            $response = Http::timeout(10)
                ->withHeaders(self::getAuthHeaders())
                ->get($endpoint);

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'valid' => false,
                'status' => 'unknown',
                'message' => 'Verification failed',
            ];
        } catch (Throwable $e) {
            self::log('error', '[VendWeave] Badge verification error', [
                'hash' => substr($hash, 0, 8) . '...',
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Renew certification before expiry.
     * 
     * @return array|null Renewal result or null on failure
     */
    public static function renewCertification(): ?array
    {
        if (!self::isEnabled()) {
            return null;
        }

        $currentStatus = self::status();
        if (!$currentStatus || empty($currentStatus['verification_hash'])) {
            return null;
        }

        try {
            $endpoint = self::getAuthorityEndpoint('/certify/renew');
            $payload = [
                'verification_hash' => $currentStatus['verification_hash'],
                'snapshot' => self::getFeatureSnapshot(),
            ];

            $response = Http::timeout(30)
                ->withHeaders(self::getAuthHeaders())
                ->post($endpoint, $payload);

            if ($response->successful()) {
                $data = $response->json();
                self::cacheStatus($data);
                self::log('info', '[VendWeave] Certification renewed', [
                    'badge_code' => $data['badge_code'] ?? null,
                    'expires_at' => $data['expires_at'] ?? null,
                ]);
                return $data;
            }

            return null;
        } catch (Throwable $e) {
            self::log('error', '[VendWeave] Certification renewal error', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if certification is revoked.
     * 
     * Revocation always overrides cache.
     */
    public static function isRevoked(): bool
    {
        try {
            return (bool) Cache::get(self::REVOCATION_CACHE_KEY, false);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Mark certification as revoked (called by webhook or manual check).
     */
    public static function markRevoked(string $reason = 'Revoked by authority'): void
    {
        try {
            Cache::forever(self::REVOCATION_CACHE_KEY, true);
            Cache::forget(self::CACHE_KEY);

            self::log('warning', '[VendWeave] Certification revoked', [
                'reason' => $reason,
            ]);
        } catch (Throwable $e) {
            // Swallow cache errors
        }
    }

    /**
     * Clear revocation status (for re-certification).
     */
    public static function clearRevocation(): void
    {
        try {
            Cache::forget(self::REVOCATION_CACHE_KEY);
        } catch (Throwable $e) {
            // Swallow cache errors
        }
    }

    /**
     * Get badge embed HTML.
     * 
     * @param string $size Badge size: small, medium, large
     * @return string HTML embed code or empty string if unavailable
     */
    public static function getBadgeHtml(string $size = 'medium'): string
    {
        $status = self::status();
        if (!$status || ($status['status'] ?? null) !== self::STATUS_ACTIVE) {
            return '';
        }

        $badgeCode = $status['badge_code'] ?? self::BADGE_BASE;
        $hash = $status['verification_hash'] ?? '';
        $projectName = $status['project_name'] ?? 'Certified Integration';

        return self::generateBadgeEmbed($badgeCode, $hash, $projectName, $size);
    }

    /**
     * Get badge image URL.
     * 
     * @param string $badgeCode Badge code
     * @param string $format Format: svg or png
     * @return string CDN URL
     */
    public static function getBadgeUrl(string $badgeCode, string $format = 'svg'): string
    {
        $cdnBase = config('vendweave.certification.cdn_url', 'https://cdn.vendweave.com/badges');
        return "{$cdnBase}/{$badgeCode}.{$format}";
    }

    /**
     * Get verification page URL.
     * 
     * @param string $hash Verification hash
     * @return string Verification URL
     */
    public static function getVerificationUrl(string $hash): string
    {
        $baseUrl = config('vendweave.certification.verify_url', 'https://vendweave.com/verify');
        return "{$baseUrl}/{$hash}";
    }

    /**
     * Get badge name from code.
     */
    public static function getBadgeName(string $badgeCode): string
    {
        return match ($badgeCode) {
            self::BADGE_CUR => 'Currency Certified',
            self::BADGE_FIN => 'Financial Certified',
            self::BADGE_GOV => 'Governance Certified',
            self::BADGE_REF => 'Reference Certified',
            self::BADGE_BASE => 'Base Certified',
            default => 'VendWeave Certified',
        };
    }

    /**
     * Get badge tier level (1-5).
     */
    public static function getBadgeTier(string $badgeCode): int
    {
        return match ($badgeCode) {
            self::BADGE_CUR => 5,
            self::BADGE_FIN => 4,
            self::BADGE_GOV => 3,
            self::BADGE_REF => 2,
            self::BADGE_BASE => 1,
            default => 0,
        };
    }

    // =========================================================================
    // QUALIFICATION CHECKS
    // =========================================================================

    /**
     * Check if integration qualifies for BASE badge.
     */
    public static function qualifiesForBase(): bool
    {
        return !empty(config('vendweave.api_key'))
            && !empty(config('vendweave.store_slug'))
            && class_exists(TransactionVerifier::class);
    }

    /**
     * Check if integration qualifies for REFERENCE badge.
     */
    public static function qualifiesForReference(): bool
    {
        return self::qualifiesForBase()
            && (bool) config('vendweave.reference_strict_mode', false);
    }

    /**
     * Check if integration qualifies for GOVERNANCE badge.
     */
    public static function qualifiesForGovernance(): bool
    {
        return self::qualifiesForReference()
            && (bool) config('vendweave.reference_governance.enabled', false)
            && class_exists(ReferenceGovernor::class)
            && ReferenceGovernor::isAvailable();
    }

    /**
     * Check if integration qualifies for FINANCIAL badge.
     */
    public static function qualifiesForFinancial(): bool
    {
        return self::qualifiesForGovernance()
            && (bool) config('vendweave.financial_reconciliation.enabled', false)
            && class_exists(FinancialRecordManager::class)
            && FinancialRecordManager::isAvailable();
    }

    /**
     * Check if integration qualifies for CURRENCY badge.
     */
    public static function qualifiesForCurrency(): bool
    {
        return self::qualifiesForFinancial()
            && !empty(config('vendweave.base_currency'))
            && class_exists(CurrencyNormalizer::class);
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * Get SDK version from composer.json.
     */
    private static function getSdkVersion(): string
    {
        try {
            $composerPath = __DIR__ . '/../../composer.json';
            if (file_exists($composerPath)) {
                $composer = json_decode(file_get_contents($composerPath), true);
                return $composer['version'] ?? 'unknown';
            }
        } catch (Throwable $e) {
            // Ignore
        }
        return 'unknown';
    }

    /**
     * Get cached certification status.
     */
    private static function getCachedStatus(): ?array
    {
        try {
            $ttl = config('vendweave.certification.cache_ttl', 3600);
            return Cache::get(self::CACHE_KEY);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Cache certification status.
     */
    private static function cacheStatus(array $status): void
    {
        try {
            $ttl = config('vendweave.certification.cache_ttl', 3600);
            Cache::put(self::CACHE_KEY, $status, $ttl);
        } catch (Throwable $e) {
            // Swallow cache errors
        }
    }

    /**
     * Fetch status from VendWeave Authority API.
     */
    private static function fetchStatusFromApi(): ?array
    {
        try {
            $domain = config('vendweave.certification.domain');
            if (empty($domain)) {
                return null;
            }

            $endpoint = self::getAuthorityEndpoint('/certify/status');
            $response = Http::timeout(10)
                ->withHeaders(self::getAuthHeaders())
                ->get($endpoint, ['domain' => $domain]);

            if ($response->successful()) {
                $data = $response->json();

                // Check for revocation in response
                if (($data['status'] ?? null) === self::STATUS_REVOKED) {
                    self::markRevoked($data['revoke_reason'] ?? 'Revoked');
                }

                self::cacheStatus($data);
                return $data;
            }

            return null;
        } catch (Throwable $e) {
            self::log('error', '[VendWeave] Failed to fetch certification status', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get Authority API endpoint.
     */
    private static function getAuthorityEndpoint(string $path): string
    {
        $base = config('vendweave.certification.authority_url', 'https://vendweave.com/api');
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Get authentication headers for Authority API.
     */
    private static function getAuthHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-VendWeave-Key' => config('vendweave.api_key', ''),
            'X-VendWeave-Store' => config('vendweave.store_slug', ''),
        ];
    }

    /**
     * Generate badge embed HTML.
     */
    private static function generateBadgeEmbed(
        string $badgeCode,
        string $hash,
        string $projectName,
        string $size = 'medium'
    ): string {
        $dimensions = match ($size) {
            'small' => ['width' => 80, 'height' => 20],
            'large' => ['width' => 200, 'height' => 60],
            default => ['width' => 150, 'height' => 40],
        };

        $badgeUrl = self::getBadgeUrl($badgeCode, 'svg');
        $verifyUrl = self::getVerificationUrl($hash);
        $badgeName = self::getBadgeName($badgeCode);
        $altText = htmlspecialchars("VendWeave {$badgeName} - {$projectName}", ENT_QUOTES);

        return <<<HTML
<a href="{$verifyUrl}" target="_blank" rel="noopener noreferrer" title="Verify VendWeave Certification">
    <img src="{$badgeUrl}" 
         alt="{$altText}" 
         width="{$dimensions['width']}" 
         height="{$dimensions['height']}" 
         style="border:0;" />
</a>
HTML;
    }

    /**
     * Log certification events.
     */
    private static function log(string $level, string $message, array $context = []): void
    {
        if (!config('vendweave.logging.enabled', true)) {
            return;
        }

        try {
            Log::channel(config('vendweave.logging.channel', 'stack'))
                ->log($level, $message, $context);
        } catch (Throwable $e) {
            // Swallow logging failures
        }
    }
}
