<?php

namespace VendWeave\Gateway\Exceptions;

/**
 * Thrown when API credentials are missing or invalid.
 */
class InvalidCredentialsException extends VendWeaveException
{
    public function __construct(string $message = 'Invalid or missing API credentials')
    {
        parent::__construct(
            $message,
            'INVALID_CREDENTIALS',
            401
        );
    }
}
