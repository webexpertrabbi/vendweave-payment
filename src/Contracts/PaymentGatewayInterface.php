<?php

namespace VendWeave\Gateway\Contracts;

use VendWeave\Gateway\Services\VerificationResult;

interface PaymentGatewayInterface
{
    /**
     * Verify a transaction against the POS API.
     *
     * @param string $orderId The unique order identifier
     * @param float $amount The exact amount to verify (no tolerance)
     * @param string $paymentMethod The payment method (bkash, nagad, rocket, upay)
     * @param string|null $trxId Optional transaction ID for direct lookup
     * @return VerificationResult
     */
    public function verify(
        string $orderId,
        float $amount,
        string $paymentMethod,
        ?string $trxId = null
    ): VerificationResult;

    /**
     * Get list of supported payment methods.
     *
     * @return array<string>
     */
    public function getPaymentMethods(): array;

    /**
     * Check if a payment method is valid.
     *
     * @param string $method
     * @return bool
     */
    public function isValidPaymentMethod(string $method): bool;

    /**
     * Get the verification URL for an order.
     *
     * @param string $orderId
     * @return string
     */
    public function getVerifyUrl(string $orderId): string;
}
