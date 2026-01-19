<?php

namespace VendWeave\Gateway;

use Illuminate\Support\Facades\Session;

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
        $reference = $reference ?? self::generateReference($storeSlug);

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
     */
    public static function clearOrderData(string $orderId): void
    {
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
}
