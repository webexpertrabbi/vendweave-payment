<?php

namespace VendWeave\Gateway\Exceptions;

use Exception;

/**
 * Thrown when the POS API is unreachable or returns an unexpected error.
 */
class ApiConnectionException extends VendWeaveException
{
    public function __construct(string $message = 'Unable to connect to VendWeave POS API', ?Exception $previous = null)
    {
        parent::__construct(
            $message,
            'API_CONNECTION_ERROR',
            503,
            [],
            $previous
        );
    }
}
