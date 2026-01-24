<?php

namespace VendWeave\Gateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use VendWeave\Gateway\Services\VerificationResult;

/**
 * Event dispatched when a payment is successfully verified.
 * 
 * Listen to this event to update your order status.
 */
class PaymentVerified
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $orderId,
        public readonly VerificationResult $result
    ) {}

    /**
     * Get the transaction ID.
     */
    public function getTrxId(): ?string
    {
        return $this->result->getTrxId();
    }

    /**
     * Get the verified amount.
     */
    public function getAmount(): ?float
    {
        return $this->result->getAmount();
    }

    /**
     * Get the payment method.
     */
    public function getPaymentMethod(): ?string
    {
        return $this->result->getPaymentMethod();
    }
}
