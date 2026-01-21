<?php

namespace VendWeave\Gateway;

use Illuminate\Support\Facades\Session;
use VendWeave\Gateway\Services\CertificationManager;
use VendWeave\Gateway\Services\ReferenceGovernor;

/**
 * Helper class for common VendWeave operations.
 * 
 * Use this in your Laravel application for convenient integration.
 */
class VendWeaveHelper
{
    /**
     * Generate a unique payment reference.
     *
     * Format: VW + 4 digits (e.g., VW3846)
     * Used to uniquely identify payments within a store.
     *
     * @param string $storeSlug Store identifier (reserved for future uniqueness checks)
     * @return string Reference code
     */
    public static function generateReference(string $storeSlug): string
    {
        return 'VW' . str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Prepare order for verification and get redirect URL.
     *
     * @param string $orderId
     * @param float $amount
     * @param string $paymentMethod
     * @param string|null $reference Optional custom reference (auto-generated if null)
     * @return string Redirect URL
     */
    public static function preparePayment(
        string $orderId,
        float $amount,
        string $paymentMethod,
        ?string $reference = null
    ): string {
        $storeSlug = config('vendweave.store_slug', 'default');
        $storeId = config('vendweave.store_id', 1);
        $ttl = config('vendweave.reference_ttl', 30);
        
        $reference = $reference ?? self::generateReference($storeSlug);

        // Reserve reference in governance engine (if enabled)
        ReferenceGovernor::reserve($storeId, $orderId, $reference, $ttl);

        // Store in session for verification page
        Session::put("vendweave_order_{$orderId}", [
            'amount' => $amount,
            'payment_method' => strtolower($paymentMethod),
            'reference' => $reference,
        ]);

        return route('vendweave.verify', ['order' => $orderId]);
    }

    /**
     * Clear stored order data.
     *
     * @param string $orderId
     * @param bool $cancelReference Whether to cancel the reference in governance
     */
    public static function clearOrderData(string $orderId, bool $cancelReference = false): void
    {
        $orderData = Session::get("vendweave_order_{$orderId}");
        
        // Cancel reference in governance if requested
        if ($cancelReference && $orderData && isset($orderData['reference'])) {
            $storeId = config('vendweave.store_id', 1);
            ReferenceGovernor::cancel($orderData['reference'], $storeId);
        }
        
        Session::forget("vendweave_order_{$orderId}");
    }

    /**
     * Get the list of supported payment methods with display info.
     *
     * @return array
     */
    public static function getPaymentMethods(): array
    {
        return [
            'bkash' => [
                'name' => 'bKash',
                'color' => '#E2136E',
                'icon' => 'bkash',
            ],
            'nagad' => [
                'name' => 'Nagad',
                'color' => '#F6A623',
                'icon' => 'nagad',
            ],
            'rocket' => [
                'name' => 'Rocket',
                'color' => '#8E44AD',
                'icon' => 'rocket',
            ],
            'upay' => [
                'name' => 'Upay',
                'color' => '#00A651',
                'icon' => 'upay',
            ],
        ];
    }

    /**
     * Check if a payment method is valid.
     *
     * @param string $method
     * @return bool
     */
    public static function isValidPaymentMethod(string $method): bool
    {
        return array_key_exists(strtolower($method), self::getPaymentMethods());
    }

    /**
     * Get certification badge HTML embed code.
     * 
     * Returns empty string if certification is disabled or not active.
     *
     * @param string $size Badge size: small, medium, large
     * @return string HTML embed code or empty string
     */
    public static function getCertificationBadge(string $size = 'medium'): string
    {
        return CertificationManager::getBadgeHtml($size);
    }

    /**
     * Get current certification status.
     * 
     * @return array|null Certification status or null if unavailable
     */
    public static function getCertificationStatus(): ?array
    {
        return CertificationManager::status();
    }

    /**
     * Detect which certification badge the current integration qualifies for.
     * 
     * @return string Badge code (e.g., VW-CERT-FIN)
     */
    public static function detectCertificationLevel(): string
    {
        return CertificationManager::detectQualifiedBadge();
    }
}
