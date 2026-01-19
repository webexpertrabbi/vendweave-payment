<?php

namespace VendWeave\Gateway\Services;

/**
 * Immutable result object for transaction verification.
 */
final class VerificationResult
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_USED = 'used';
    public const STATUS_EXPIRED = 'expired';

    private function __construct(
        private readonly string $status,
        private readonly ?string $trxId,
        private readonly ?float $amount,
        private readonly ?string $paymentMethod,
        private readonly ?string $storeSlug,
        private readonly ?string $errorCode,
        private readonly ?string $errorMessage,
        private readonly ?string $referenceStatus = null,
        private readonly ?string $referenceCreatedAt = null,
        private readonly ?string $referenceExpiresAt = null
    ) {}

    /**
     * Create a successful verification result.
     */
    public static function confirmed(
        string $trxId,
        float $amount,
        string $paymentMethod,
        string $storeSlug,
        ?string $referenceStatus = null,
        ?string $referenceCreatedAt = null,
        ?string $referenceExpiresAt = null
    ): self {
        return new self(
            self::STATUS_CONFIRMED,
            $trxId,
            $amount,
            $paymentMethod,
            $storeSlug,
            null,
            null,
            $referenceStatus,
            $referenceCreatedAt,
            $referenceExpiresAt
        );
    }

    /**
     * Create a pending verification result.
     */
    public static function pending(?string $message = null): self
    {
        return new self(
            self::STATUS_PENDING,
            null,
            null,
            null,
            null,
            null,
            $message
        );
    }

    /**
     * Create a failed verification result.
     */
    public static function failed(string $errorCode, string $errorMessage): self
    {
        return new self(
            self::STATUS_FAILED,
            null,
            null,
            null,
            null,
            $errorCode,
            $errorMessage
        );
    }

    /**
     * Create a "transaction already used" result.
     */
    public static function alreadyUsed(string $trxId): self
    {
        return new self(
            self::STATUS_USED,
            $trxId,
            null,
            null,
            null,
            'TRANSACTION_ALREADY_USED',
            "Transaction {$trxId} has already been used for another order"
        );
    }

    /**
     * Create an "expired" result.
     */
    public static function expired(string $trxId): self
    {
        return new self(
            self::STATUS_EXPIRED,
            $trxId,
            null,
            null,
            null,
            'TRANSACTION_EXPIRED',
            "Transaction {$trxId} has expired"
        );
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTrxId(): ?string
    {
        return $this->trxId;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function getStoreSlug(): ?string
    {
        return $this->storeSlug;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * Get reference lifecycle status.
     */
    public function getReferenceStatus(): ?string
    {
        return $this->referenceStatus;
    }

    /**
     * Get reference creation timestamp.
     */
    public function getReferenceCreatedAt(): ?string
    {
        return $this->referenceCreatedAt;
    }

    /**
     * Get reference expiry timestamp.
     */
    public function getReferenceExpiresAt(): ?string
    {
        return $this->referenceExpiresAt;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return in_array($this->status, [
            self::STATUS_FAILED,
            self::STATUS_USED,
            self::STATUS_EXPIRED,
        ]);
    }

    /**
     * Convert to array for JSON response.
     */
    public function toArray(): array
    {
        $data = [
            'status' => $this->status,
        ];

        if ($this->trxId !== null) {
            $data['trx_id'] = $this->trxId;
        }

        if ($this->amount !== null) {
            $data['amount'] = $this->amount;
        }

        if ($this->paymentMethod !== null) {
            $data['payment_method'] = $this->paymentMethod;
        }

        if ($this->errorCode !== null) {
            $data['error_code'] = $this->errorCode;
            $data['error_message'] = $this->errorMessage;
        }

        // Reference lifecycle data
        if ($this->referenceStatus !== null) {
            $data['reference_status'] = $this->referenceStatus;
        }
        if ($this->referenceCreatedAt !== null) {
            $data['reference_created_at'] = $this->referenceCreatedAt;
        }
        if ($this->referenceExpiresAt !== null) {
            $data['reference_expires_at'] = $this->referenceExpiresAt;
        }

        return $data;
    }
}
