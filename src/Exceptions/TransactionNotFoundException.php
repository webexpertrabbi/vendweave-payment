<?php

namespace VendWeave\Gateway\Exceptions;

/**
 * Thrown when a transaction ID is not found in the POS system.
 */
class TransactionNotFoundException extends VendWeaveException
{
    public function __construct(string $trxId, array $context = [])
    {
        parent::__construct(
            "Transaction not found: {$trxId}",
            'TRANSACTION_NOT_FOUND',
            404,
            array_merge(['trx_id' => $trxId], $context)
        );
    }
}
