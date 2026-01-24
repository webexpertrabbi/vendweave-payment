<?php

namespace VendWeave\Gateway\Exceptions;

use Exception;

/**
 * Base exception for all VendWeave gateway errors.
 * This should never be thrown directly - use specific child exceptions.
 */
class VendWeaveException extends Exception
{
    /**
     * The error code for API responses.
     */
    protected string $errorCode;

    /**
     * Additional context for debugging.
     */
    protected array $context = [];

    public function __construct(
        string $message = '',
        string $errorCode = 'VENDWEAVE_ERROR',
        int $httpCode = 400,
        array $context = [],
        ?Exception $previous = null
    ) {
        $this->errorCode = $errorCode;
        $this->context = $context;

        parent::__construct($message, $httpCode, $previous);
    }

    /**
     * Get the error code for API responses.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get additional context.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Convert exception to array for JSON response.
     */
    public function toArray(): array
    {
        return [
            'error' => true,
            'error_code' => $this->errorCode,
            'message' => $this->getMessage(),
        ];
    }
}
