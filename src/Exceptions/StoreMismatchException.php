<?php

namespace VendWeave\Gateway\Exceptions;

/**
 * Thrown when the store ID does not match (store scope isolation).
 */
class StoreMismatchException extends VendWeaveException
{
    public function __construct(int $expected, int $received, array $context = [])
    {
        parent::__construct(
            "Store scope violation: transaction belongs to store {$received}, expected {$expected}",
            'STORE_MISMATCH',
            403,
            array_merge([
                'expected_store' => $expected,
                'received_store' => $received,
            ], $context)
        );
    }
}
