<?php

namespace VendWeave\Gateway\Services;

use VendWeave\Gateway\Contracts\PaymentGatewayInterface;

/**
 * High-level payment operations manager.
 * 
 * This is the primary service exposed to Laravel applications.
 * It coordinates between the API client and transaction verifier.
 */
class PaymentManager implements PaymentGatewayInterface
{
    public function __construct(
        private readonly TransactionVerifier $verifier
    ) {}

    /**
     * Verify a transaction against the POS API.
     *
     * @param string $orderId The unique order identifier
     * @param float $amount The exact amount to verify (no tolerance)
     * @param string $paymentMethod The payment method (bkash, nagad, rocket, upay)
     * @param string|null $trxId Optional transaction ID for direct lookup
     * @param string|null $reference Optional payment reference for matching
     * @return VerificationResult
     */
    public function verify(
        string $orderId,
        float $amount,
        string $paymentMethod,
        ?string $trxId = null,
        ?string $reference = null
    ): VerificationResult {
        // Normalize payment method
        $paymentMethod = strtolower(trim($paymentMethod));

        // Validate payment method
        if (!$this->isValidPaymentMethod($paymentMethod)) {
            return VerificationResult::failed(
                'INVALID_PAYMENT_METHOD',
                "Invalid payment method: {$paymentMethod}. Supported: " . implode(', ', $this->getPaymentMethods())
            );
        }

        return $this->verifier->verify($orderId, $amount, $paymentMethod, $trxId, $reference);
    }

    /**
     * Reserve a payment reference with POS (optional, safe).
     *
     * @param string $orderId
     * @param float $amount
     * @param string $paymentMethod
     * @param string $reference
     * @return array|null
     */
    public function reserveReference(
        string $orderId,
        float $amount,
        string $paymentMethod,
        string $reference
    ): ?array {
        return $this->verifier->reserveReference($orderId, $amount, $paymentMethod, $reference);
    }

    /**
     * Get list of supported payment methods.
     *
     * @return array<string>
     */
    public function getPaymentMethods(): array
    {
        $methods = config('vendweave.payment_methods', [
            'bkash', 'nagad', 'rocket', 'upay'
        ]);

        // If config is associative (keyed by method name), return keys
        if (array_keys($methods) !== range(0, count($methods) - 1)) {
            return array_keys($methods);
        }

        return $methods;
    }

    /**
     * Check if a payment method is valid.
     *
     * @param string $method
     * @return bool
     */
    public function isValidPaymentMethod(string $method): bool
    {
        // Handle case where method might be passed as "bkash" but keys are "bkash"
        return in_array(strtolower($method), $this->getPaymentMethods());
    }

    /**
     * Get the verification URL for an order.
     *
     * @param string $orderId
     * @return string
     */
    public function getVerifyUrl(string $orderId): string
    {
        return route('vendweave.verify', ['order' => $orderId]);
    }

    /**
     * Get payment method display information.
     *
     * @return array<string, array>
     */
    public function getPaymentMethodsInfo(): array
    {
        $defaults = [
            'bkash' => [
                'name' => 'bKash',
                'color' => '#E2136E',
                'logo' => 'https://raw.githubusercontent.com/webexpertrabbi/vendweave-assets/main/bkash.png',
            ],
            'nagad' => [
                'name' => 'Nagad',
                'color' => '#F6A623',
                'logo' => 'https://raw.githubusercontent.com/webexpertrabbi/vendweave-assets/main/nagad.png',
            ],
            'rocket' => [
                'name' => 'Rocket',
                'color' => '#8E44AD',
                'logo' => 'https://raw.githubusercontent.com/webexpertrabbi/vendweave-assets/main/rocket.png',
            ],
            'upay' => [
                'name' => 'Upay',
                'color' => '#00A651',
                'logo' => 'https://raw.githubusercontent.com/webexpertrabbi/vendweave-assets/main/upay.png',
            ],
        ];

        $configMethods = config('vendweave.payment_methods', []);
        
        // Return structured info merging defaults with config
        $info = [];
        foreach ($this->getPaymentMethods() as $method) {
            $base = $defaults[$method] ?? ['name' => ucfirst($method), 'color' => '#333333'];
            $config = $configMethods[$method] ?? [];
            
            // If config is just a list of strings
            if (!is_array($config)) {
                $config = [];
            }

            $info[$method] = array_merge($base, $config);
        }

        return $info;
    }
}
