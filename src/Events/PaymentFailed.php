<?php

namespace VendWeave\Gateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a payment verification fails.
 */
class PaymentFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $orderId,
        public readonly string $errorCode,
        public readonly string $errorMessage
    ) {}
}
