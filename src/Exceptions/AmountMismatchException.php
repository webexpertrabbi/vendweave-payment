<?php

namespace VendWeave\Gateway\Exceptions;

/**
 * Thrown when the transaction amount does not match exactly.
 * No tolerance allowed - must match to the paisa.
 */
class AmountMismatchException extends VendWeaveException
{
    public function __construct(float $expected, float $received, array $context = [])
    {
        parent::__construct(
            "Amount mismatch: expected {$expected}, received {$received}",
            'AMOUNT_MISMATCH',
            400,
            array_merge([
                'expected_amount' => $expected,
                'received_amount' => $received,
            ], $context)
        );
    }
}
