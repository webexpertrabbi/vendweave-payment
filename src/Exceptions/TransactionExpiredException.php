<?php

namespace VendWeave\Gateway\Exceptions;

/**
 * Thrown when a transaction has expired beyond the allowed timeframe.
 */
class TransactionExpiredException extends VendWeaveException
{
    public function __construct(string $trxId, array $context = [])
    {
        parent::__construct(
            "Transaction expired: {$trxId}",
            'TRANSACTION_EXPIRED',
            410,
            array_merge(['trx_id' => $trxId], $context)
        );
    }
}
