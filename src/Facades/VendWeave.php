<?php

namespace VendWeave\Gateway\Facades;

use Illuminate\Support\Facades\Facade;
use VendWeave\Gateway\Contracts\PaymentGatewayInterface;
use VendWeave\Gateway\Services\CertificationManager;

/**
 * @method static \VendWeave\Gateway\Services\VerificationResult verify(string $orderId, float $amount, string $paymentMethod, ?string $trxId = null)
 * @method static array getPaymentMethods()
 * @method static bool isValidPaymentMethod(string $method)
 *
 * @see \VendWeave\Gateway\Services\PaymentManager
 */
class VendWeave extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return PaymentGatewayInterface::class;
    }

    /**
     * Get current certification status.
     * 
     * @return array|null Certification status or null if unavailable
     */
    public static function certificationStatus(): ?array
    {
        return CertificationManager::status();
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
        return CertificationManager::requestCertification($domain, $projectName);
    }

    /**
     * Verify a badge hash with VendWeave Authority.
     * 
     * @param string $hash Verification hash
     * @return array|null Verification result or null on failure
     */
    public static function verifyBadge(string $hash): ?array
    {
        return CertificationManager::verifyBadge($hash);
    }

    /**
     * Renew certification before expiry.
     * 
     * @return array|null Renewal result or null on failure
     */
    public static function renewCertification(): ?array
    {
        return CertificationManager::renewCertification();
    }

    /**
     * Get badge embed HTML for display.
     * 
     * @param string $size Badge size: small, medium, large
     * @return string HTML embed code or empty string
     */
    public static function getBadgeHtml(string $size = 'medium'): string
    {
        return CertificationManager::getBadgeHtml($size);
    }

    /**
     * Detect which badge the current integration qualifies for.
     * 
     * @return string Badge code
     */
    public static function detectQualifiedBadge(): string
    {
        return CertificationManager::detectQualifiedBadge();
    }

    /**
     * Get feature snapshot for certification.
     * 
     * @return array Feature detection results
     */
    public static function getFeatureSnapshot(): array
    {
        return CertificationManager::getFeatureSnapshot();
    }
}
