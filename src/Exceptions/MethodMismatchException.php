<?php

namespace VendWeave\Gateway\Exceptions;

/**
 * Thrown when the payment method does not match.
 */
class MethodMismatchException extends VendWeaveException
{
    public function __construct(string $expected, string $received, array $context = [])
    {
        parent::__construct(
            "Payment method mismatch: expected {$expected}, received {$received}",
            'METHOD_MISMATCH',
            400,
            array_merge([
                'expected_method' => $expected,
                'received_method' => $received,
            ], $context)
        );
    }
}
