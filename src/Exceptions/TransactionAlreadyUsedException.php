<?php

namespace VendWeave\Gateway\Exceptions;

/**
 * Thrown when a transaction has already been used for another order.
 * Prevents double-spending/transaction reuse attacks.
 */
class TransactionAlreadyUsedException extends VendWeaveException
{
    public function __construct(string $trxId, array $context = [])
    {
        parent::__construct(
            "Transaction already used: {$trxId}",
            'TRANSACTION_ALREADY_USED',
            409,
            array_merge(['trx_id' => $trxId], $context)
        );
    }
}
