<?php

namespace VendWeave\Gateway\Services;

use VendWeave\Gateway\Exceptions\AmountMismatchException;
use VendWeave\Gateway\Exceptions\ApiConnectionException;
use VendWeave\Gateway\Exceptions\MethodMismatchException;
use VendWeave\Gateway\Exceptions\StoreMismatchException;
use VendWeave\Gateway\Exceptions\TransactionAlreadyUsedException;
use VendWeave\Gateway\Exceptions\TransactionExpiredException;
use VendWeave\Gateway\Exceptions\TransactionNotFoundException;
use Illuminate\Support\Facades\Log;
use VendWeave\Gateway\Services\ReferenceGovernor;
use VendWeave\Gateway\Services\FinancialRecordManager;

/**
 * Transaction verification service.
 * 
 * This service validates transactions against the POS API response.
 * It enforces strict matching rules:
 * - Exact amount matching (no tolerance)
 * - Payment method matching
 * - Store scope isolation
 * - Transaction reuse prevention
 */
class TransactionVerifier
{
    public function __construct(
        private readonly VendWeaveApiClient $apiClient
    ) {}

    /**
     * Verify a transaction against the POS system.
     *
     * @param string $orderId
     * @param float $expectedAmount
     * @param string $expectedMethod
     * @param string|null $trxId
     * @param string|null $expectedReference Payment reference for matching
     * @return VerificationResult
     */
    public function verify(
        string $orderId,
        float $expectedAmount,
        string $expectedMethod,
        ?string $trxId = null,
        ?string $expectedReference = null
    ): VerificationResult {
        try {
            $response = $this->apiClient->pollTransaction(
                $orderId,
                $expectedAmount,
                $expectedMethod,
                $trxId,
                $expectedReference
            );

            $status = $response['status'] ?? 'unknown';
            $resolvedTrxId = $trxId ?? ($response['trx_id'] ?? null);

            // If POS is still pending but we have a TRX ID, escalate to verify
            if ($status === 'pending' && $resolvedTrxId) {
                $verifyResponse = $this->apiClient->verifyTransaction(
                    $orderId,
                    $expectedAmount,
                    $expectedMethod,
                    $resolvedTrxId,
                    $expectedReference
                );

                return $this->processResponse(
                    $verifyResponse,
                    $expectedAmount,
                    $expectedMethod,
                    $orderId,
                    $expectedReference
                );
            }

            return $this->processResponse(
                $response,
                $expectedAmount,
                $expectedMethod,
                $orderId,
                $expectedReference
            );

        } catch (ApiConnectionException $e) {
            return VerificationResult::failed(
                'API_ERROR',
                $e->getMessage()
            );
        }
    }

    /**
     * Reserve a payment reference with POS (optional, safe).
     *
     * @param string $orderId
     * @param float $amount
     * @param string $paymentMethod
     * @param string $reference
     * @return array|null
     */
    public function reserveReference(
        string $orderId,
        float $amount,
        string $paymentMethod,
        string $reference
    ): ?array {
        try {
            return $this->apiClient->reserveReference(
                $orderId,
                $amount,
                $paymentMethod,
                $reference
            );
        } catch (ApiConnectionException $e) {
            return null;
        }
    }

    /**
     * Process the API response and validate all matching criteria.
     *
     * @param array $response
     * @param float $expectedAmount
     * @param string $expectedMethod
     * @param string|null $expectedReference
     * @return VerificationResult
     */
    private function processResponse(
        array $response,
        float $expectedAmount,
        string $expectedMethod,
        string $orderId,
        ?string $expectedReference = null
    ): VerificationResult {
        // Handle HTTP status codes
        $httpStatus = $response['_http_status'] ?? 200;

        if ($httpStatus === 404) {
            return VerificationResult::failed(
                'TRANSACTION_NOT_FOUND',
                'No matching transaction found'
            );
        }

        // Get status from response
        $status = $response['status'] ?? 'unknown';
        $rawStatus = $response['raw_status'] ?? $status;

        // Handle POS status values
        switch ($status) {
            case 'pending':
                return VerificationResult::pending('Transaction is pending verification');

            case 'used':
                return VerificationResult::alreadyUsed($response['trx_id'] ?? 'unknown');

            case 'expired':
                return VerificationResult::expired($response['trx_id'] ?? 'unknown');

            case 'failed':
                return VerificationResult::failed(
                    'TRANSACTION_FAILED',
                    $response['message'] ?? 'Transaction failed'
                );

            case 'confirmed':
                return $this->validateConfirmedTransaction(
                    $response,
                    $expectedAmount,
                    $expectedMethod,
                    $orderId,
                    $expectedReference
                );

            default:
                // Graceful fallback: treat any unrecognized status as pending
                // SDK should never throw "unknown status" to clients
                Log::warning('[VendWeave] Unrecognized status in TransactionVerifier, treating as pending', [
                    'status' => $status,
                    'raw_status' => $rawStatus,
                    'order_id' => $orderId,
                ]);
                return VerificationResult::pending('Transaction status pending verification');
        }
    }

    /**
     * Validate a confirmed transaction against expected values.
     *
     * @param array $response
     * @param float $expectedAmount
     * @param string $expectedMethod
     * @param string|null $expectedReference
     * @return VerificationResult
     */
    private function validateConfirmedTransaction(
        array $response,
        float $expectedAmount,
        string $expectedMethod,
        string $orderId,
        ?string $expectedReference = null
    ): VerificationResult {
        $status = $response['status'] ?? 'confirmed';
        $rawStatus = $response['raw_status'] ?? $status;
        $trxId = $response['trx_id'] ?? null;
        $receivedAmount = (float) ($response['amount'] ?? 0);
        $receivedMethod = strtolower($response['payment_method'] ?? '');
        $receivedStoreSlug = $response['store_slug'] ?? null;
        $expectedStoreSlug = $this->apiClient->getStoreSlug();
        $receivedCurrency = $response['currency'] ?? null;

        // 1. Validate store scope isolation (SECURITY CHECK with graceful degradation)
        if ($expectedStoreSlug !== null && $receivedStoreSlug !== null) {
            // Both exist - strict validation
            if ($receivedStoreSlug !== $expectedStoreSlug) {
                return VerificationResult::failed(
                    'STORE_MISMATCH',
                    "Transaction belongs to store {$receivedStoreSlug}, expected {$expectedStoreSlug}"
                );
            }
        } elseif ($expectedStoreSlug !== null && $receivedStoreSlug === null) {
            // API didn't return store_slug - log warning but don't fail
            // The SDK already injected it in VendWeaveApiClient::normalizeResponse()
            // but we log for transparency
            Log::warning('[VendWeave] API response missing store_slug - graceful degradation active', [
                'expected_store_slug' => $expectedStoreSlug,
                'trx_id' => $trxId,
                'message' => 'POS API should return store_slug. SDK injected from config but validation skipped.',
            ]);
        }

        // 2. Reference Identity Protocol (Phase 3)
        // POS is source of truth, SDK enforces based on reference_status
        $strictMode = config('vendweave.reference_strict_mode', false);
        $referenceStatus = 'skipped'; // Default: no reference validation
        $receivedReference = $response['reference'] ?? null;
        $posReferenceStatus = $response['reference_status'] ?? null; // POS-provided status

        // Log context for all reference operations
        $referenceCreatedAt = $response['reference_created_at'] ?? null;
        $referenceExpiresAt = $response['reference_expires_at'] ?? null;

        $logContext = [
            'status' => $status,
            'raw_status' => $rawStatus,
            'expected_reference' => $expectedReference,
            'payment_reference' => $receivedReference,
            'received_reference' => $receivedReference,
            'pos_reference_status' => $posReferenceStatus,
            'reference_created_at' => $referenceCreatedAt,
            'reference_expires_at' => $referenceExpiresAt,
            'trx_id' => $trxId,
            'strict_mode' => $strictMode,
        ];

        // 2a. Check POS reference_status first (expired/replayed detection)
        if ($posReferenceStatus !== null) {
            switch ($posReferenceStatus) {
                case 'expired':
                    $referenceStatus = 'expired';
                    Log::warning('[VendWeave] Reference expired (POS reported)', array_merge($logContext, [
                        'reference_status' => $referenceStatus,
                    ]));
                    return VerificationResult::failed(
                        'REFERENCE_EXPIRED',
                        "Reference {$receivedReference} has expired"
                    );

                case 'replayed':
                case 'used':
                    $referenceStatus = 'replayed';
                    Log::warning('[VendWeave] Reference replay detected (POS reported)', array_merge($logContext, [
                        'reference_status' => $referenceStatus,
                    ]));
                    return VerificationResult::failed(
                        'REFERENCE_REPLAY',
                        "Reference {$receivedReference} has already been used"
                    );

                case 'mismatched':
                    $referenceStatus = 'mismatched';
                    Log::warning('[VendWeave] Reference mismatch (POS reported)', array_merge($logContext, [
                        'reference_status' => $referenceStatus,
                    ]));
                    return VerificationResult::failed(
                        'REFERENCE_MISMATCH',
                        "Reference mismatch reported by POS"
                    );

                case 'matched':
                    $referenceStatus = 'matched';
                    Log::info('[VendWeave] Reference matched (POS confirmed)', array_merge($logContext, [
                        'reference_status' => $referenceStatus,
                    ]));
                    break;

                case 'cancelled':
                    $referenceStatus = 'cancelled';
                    Log::warning('[VendWeave] Reference cancelled (POS reported)', array_merge($logContext, [
                        'reference_status' => $referenceStatus,
                    ]));
                    return VerificationResult::failed(
                        'REFERENCE_CANCELLED',
                        "Reference {$receivedReference} has been cancelled"
                    );
            }
        }

        // 2b. SDK-side reference validation (if POS didn't provide status)
        if ($posReferenceStatus === null) {
            if ($strictMode && $expectedReference !== null) {
                // STRICT MODE: Reference MUST match, no fallback
                if ($receivedReference === null) {
                    $referenceStatus = 'missing';
                    Log::warning('[VendWeave] Strict mode: No reference in response', array_merge($logContext, [
                        'reference_status' => $referenceStatus,
                    ]));
                    return VerificationResult::failed(
                        'REFERENCE_MISSING',
                        "Strict mode enabled: Reference expected but not received"
                    );
                }
                if ($receivedReference !== $expectedReference) {
                    $referenceStatus = 'mismatched';
                    Log::warning('[VendWeave] Strict mode: Reference mismatch', array_merge($logContext, [
                        'reference_status' => $referenceStatus,
                    ]));
                    return VerificationResult::failed(
                        'REFERENCE_MISMATCH',
                        "Reference mismatch: expected {$expectedReference}, received {$receivedReference}"
                    );
                }
                $referenceStatus = 'matched';
                Log::info('[VendWeave] Strict mode: Reference matched', array_merge($logContext, [
                    'reference_status' => $referenceStatus,
                ]));
            } elseif ($expectedReference !== null && $receivedReference !== null) {
                // NON-STRICT: Validate if both present
                if ($receivedReference !== $expectedReference) {
                    $referenceStatus = 'mismatched';
                    Log::warning('[VendWeave] Reference mismatch detected', array_merge($logContext, [
                        'reference_status' => $referenceStatus,
                    ]));
                    return VerificationResult::failed(
                        'REFERENCE_MISMATCH',
                        "Reference mismatch: expected {$expectedReference}, received {$receivedReference}"
                    );
                }
                $referenceStatus = 'matched';
                Log::info('[VendWeave] Reference matched successfully', array_merge($logContext, [
                    'reference_status' => $referenceStatus,
                ]));
            } else {
                // Reference validation skipped (backward compatibility)
                Log::debug('[VendWeave] Reference validation skipped', array_merge($logContext, [
                    'reference_status' => $referenceStatus,
                ]));
            }
        }

        // 2. Validate exact amount match (NO TOLERANCE - CRITICAL)
        if (!$this->amountsMatch($expectedAmount, $receivedAmount)) {
            return VerificationResult::failed(
                'AMOUNT_MISMATCH',
                "Amount mismatch: expected {$expectedAmount}, received {$receivedAmount}"
            );
        }

        // 3. Validate payment method match
        if (empty($receivedMethod)) {
             Log::warning('[VendWeave] Payment method missing in response during verification. Allowing due to strict amount match.', [
                'expected' => $expectedMethod,
                'received' => $receivedMethod,
                'trx_id' => $trxId
             ]);
             // Graceful degradation: assume it matches
        } elseif (strtolower($expectedMethod) !== $receivedMethod) {
            return VerificationResult::failed(
                'METHOD_MISMATCH',
                "Payment method mismatch: expected {$expectedMethod}, received {$receivedMethod}"
            );
        }

        // 4. Reference governance (Phase-5) - only if migration exists
        $referenceForGovernance = $receivedReference ?? $expectedReference ?? $trxId ?? null;
        if ($referenceForGovernance !== null && class_exists(ReferenceGovernor::class) && ReferenceGovernor::isAvailable()) {
            $governedStatus = ReferenceGovernor::validate(
                $referenceForGovernance,
                $orderId,
                $receivedStoreSlug ?? $expectedStoreSlug
            );

            switch ($governedStatus) {
                case ReferenceGovernor::STATUS_MATCHED:
                case ReferenceGovernor::STATUS_REPLAYED:
                    return VerificationResult::failed(
                        'REFERENCE_REPLAY',
                        "Reference {$trxId} has already been used"
                    );

                case ReferenceGovernor::STATUS_EXPIRED:
                    return VerificationResult::failed(
                        'REFERENCE_EXPIRED',
                        "Reference {$trxId} has expired"
                    );

                case ReferenceGovernor::STATUS_CANCELLED:
                    return VerificationResult::failed(
                        'REFERENCE_CANCELLED',
                        "Reference {$trxId} was cancelled"
                    );

                case ReferenceGovernor::STATUS_RESERVED:
                    ReferenceGovernor::match($referenceForGovernance);
                    break;
            }
        }

        // 5. Financial reconciliation (Phase-6) - only if migration exists
        if (class_exists(FinancialRecordManager::class) && FinancialRecordManager::isAvailable()) {
            $financialReference = $receivedReference ?? $expectedReference ?? $trxId ?? $orderId;
            FinancialRecordManager::createFromReference(
                $financialReference,
                $orderId,
                $receivedStoreSlug ?? $expectedStoreSlug,
                $expectedAmount,
                $receivedAmount,
                $receivedMethod ?: $expectedMethod,
                $trxId,
                array_merge($response, [
                    'currency' => $receivedCurrency ?? config('vendweave.base_currency', 'USD'),
                    'base_currency' => config('vendweave.base_currency', 'USD'),
                ])
            );
        }

        // All validations passed
        return VerificationResult::confirmed(
            $trxId,
            $receivedAmount,
            $receivedMethod,
            $receivedStoreSlug ?? $expectedStoreSlug,
            $referenceStatus,
            $referenceCreatedAt,
            $referenceExpiresAt
        );
    }

    /**
     * Compare amounts with precision handling.
     * Uses 2 decimal places for BDT currency.
     * NO tolerance - must match exactly.
     *
     * @param float $expected
     * @param float $received
     * @return bool
     */
    private function amountsMatch(float $expected, float $received): bool
    {
        // Round to 2 decimal places for comparison
        $expectedRounded = round($expected, 2);
        $receivedRounded = round($received, 2);

        return $expectedRounded === $receivedRounded;
    }
}
