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

            // VALID POS states for Laravel API: pending, verified, used
            // If POS returns success/confirmed, it's an invalid state for Laravel SDK
            if (in_array($status, ['success', 'confirmed'])) {
                Log::warning('[VendWeave] Invalid POS state for Laravel API - received legacy status', [
                    'status' => $status,
                    'expected' => ['pending', 'verified', 'used'],
                    'order_id' => $orderId,
                    'reference' => $expectedReference,
                    'trx_id' => $resolvedTrxId,
                    'action' => 'Escalating to confirm-transaction',
                ]);
            }

            // If POS returns success/confirmed directly, escalate to confirm-transaction
            if (in_array($status, ['success', 'confirmed']) && $resolvedTrxId) {
                Log::info('[VendWeave] Escalating to confirm-transaction (poll returned success/confirmed)', [
                    'reference' => $expectedReference,
                    'trx_id' => $resolvedTrxId,
                    'status' => $status,
                    'order_id' => $orderId,
                ]);

                $resolvedReferenceForConfirm = $this->resolveReferenceForConfirm($response, $expectedReference);

                $confirmResponse = $this->apiClient->confirmTransaction(
                    $resolvedTrxId,
                    $resolvedReferenceForConfirm,
                    $orderId
                );

                // Enrich response to preserve context for finalization
                if (!isset($confirmResponse['trx_id'])) {
                    $confirmResponse['trx_id'] = $resolvedTrxId;
                }
                if (!isset($confirmResponse['reference']) && $resolvedReferenceForConfirm !== null) {
                    $confirmResponse['reference'] = $resolvedReferenceForConfirm;
                }
                if (!isset($confirmResponse['order_id'])) {
                    $confirmResponse['order_id'] = $orderId;
                }

                return $this->processResponse(
                    $confirmResponse,
                    $expectedAmount,
                    $expectedMethod,
                    $orderId,
                    $resolvedReferenceForConfirm
                );
            }

            // If POS is still pending but we have a TRX ID, escalate to verify
            if ($status === 'pending' && $resolvedTrxId) {
                $referenceForValidation = $expectedReference;

                $verifyResponse = $this->apiClient->verifyTransaction(
                    $orderId,
                    $expectedAmount,
                    $expectedMethod,
                    $resolvedTrxId,
                    $expectedReference
                );

                if ($expectedReference !== null && $this->shouldAttemptReferenceFallback($verifyResponse)) {
                    Log::warning('[VendWeave] Verify returned reference-related failure, retrying without reference', [
                        'order_id' => $orderId,
                        'trx_id' => $resolvedTrxId,
                        'expected_reference' => $expectedReference,
                        'error_code' => $verifyResponse['error_code'] ?? null,
                        'reference_status' => $verifyResponse['reference_status'] ?? null,
                    ]);

                    $verifyResponse = $this->apiClient->verifyTransaction(
                        $orderId,
                        $expectedAmount,
                        $expectedMethod,
                        $resolvedTrxId,
                        null
                    );

                    $referenceForValidation = null;
                }

                // NEW: Confirm phase required after verify returns verified or confirmed
                // Laravel SDK returns 'verified', WooCommerce might return 'confirmed'
                $verifyStatus = $verifyResponse['status'] ?? null;
                if (in_array($verifyStatus, ['verified', 'confirmed'])) {
                    $resolvedReferenceForConfirm = $this->resolveReferenceForConfirm($verifyResponse, $expectedReference);

                    Log::info('[VendWeave] Calling confirm-transaction', [
                        'reference' => $resolvedReferenceForConfirm,
                        'trx_id' => $resolvedTrxId,
                        'previous_status' => $verifyResponse['raw_status'] ?? $verifyResponse['status'] ?? null,
                    ]);
                    $confirmResponse = $this->apiClient->confirmTransaction(
                        $resolvedTrxId,
                        $resolvedReferenceForConfirm,
                        $orderId
                    );

                    // Enrich response to preserve context for finalization
                    if (!isset($confirmResponse['trx_id'])) {
                        $confirmResponse['trx_id'] = $resolvedTrxId;
                    }
                    if (!isset($confirmResponse['reference']) && $resolvedReferenceForConfirm !== null) {
                        $confirmResponse['reference'] = $resolvedReferenceForConfirm;
                    }
                    if (!isset($confirmResponse['order_id'])) {
                        $confirmResponse['order_id'] = $orderId;
                    }

                    return $this->processResponse(
                        $confirmResponse,
                        $expectedAmount,
                        $expectedMethod,
                        $orderId,
                        $resolvedReferenceForConfirm
                    );
                }

                return $this->processResponse(
                    $verifyResponse,
                    $expectedAmount,
                    $expectedMethod,
                    $orderId,
                    $referenceForValidation
                );
            }

            if ($resolvedTrxId && $expectedReference !== null && $status === 'failed' && $this->shouldAttemptReferenceFallback($response)) {
                Log::warning('[VendWeave] Poll returned reference-related failure, retrying verify without reference', [
                    'order_id' => $orderId,
                    'trx_id' => $resolvedTrxId,
                    'expected_reference' => $expectedReference,
                    'error_code' => $response['error_code'] ?? null,
                    'reference_status' => $response['reference_status'] ?? null,
                ]);

                $verifyResponse = $this->apiClient->verifyTransaction(
                    $orderId,
                    $expectedAmount,
                    $expectedMethod,
                    $resolvedTrxId,
                    null
                );

                $verifyStatus = $verifyResponse['status'] ?? null;
                if (in_array($verifyStatus, ['verified', 'confirmed'])) {
                    $resolvedReferenceForConfirm = $this->resolveReferenceForConfirm($verifyResponse, null);
                    $confirmResponse = $this->apiClient->confirmTransaction(
                        $resolvedTrxId,
                        $resolvedReferenceForConfirm,
                        $orderId
                    );

                    if (!isset($confirmResponse['trx_id'])) {
                        $confirmResponse['trx_id'] = $resolvedTrxId;
                    }
                    if (!isset($confirmResponse['reference']) && $resolvedReferenceForConfirm !== null) {
                        $confirmResponse['reference'] = $resolvedReferenceForConfirm;
                    }
                    if (!isset($confirmResponse['order_id'])) {
                        $confirmResponse['order_id'] = $orderId;
                    }

                    return $this->processResponse(
                        $confirmResponse,
                        $expectedAmount,
                        $expectedMethod,
                        $orderId,
                        $resolvedReferenceForConfirm
                    );
                }

                return $this->processResponse(
                    $verifyResponse,
                    $expectedAmount,
                    $expectedMethod,
                    $orderId,
                    null
                );
            }

            // Diagnostic warning: pending with reference but no trx_id from POS
            if ($status === 'pending' && $expectedReference && !$resolvedTrxId) {
                Log::warning('[VendWeave] Awaiting trx_id from POS', [
                    'reference' => $expectedReference,
                    'status' => 'pending',
                    'order_id' => $orderId,
                    'awaiting_trx_id_from_pos' => true,
                ]);
            }

            // Log error for completely unexpected/unknown states
            if (!in_array($status, ['pending', 'verified', 'used', 'success', 'confirmed', 'failed', 'expired'])) {
                Log::error('[VendWeave] Unknown POS status received', [
                    'status' => $status,
                    'expected' => ['pending', 'verified', 'used', 'failed', 'expired'],
                    'order_id' => $orderId,
                    'reference' => $expectedReference,
                    'raw_response' => $response,
                ]);
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
     * Verify a transaction using only TRX ID (no reference required).
     *
     * This is used for manual fallback verification when the user did not
     * include the reference number in their payment. Only TRX ID, amount,
     * payment method and store slug are matched — no reference check.
     *
     * @param string $orderId
     * @param float $expectedAmount
     * @param string $expectedMethod
     * @param string $trxId
     * @return VerificationResult
     */
    public function verifyByTrxId(
        string $orderId,
        float $expectedAmount,
        string $expectedMethod,
        string $trxId
    ): VerificationResult {
        try {
            Log::info('[VendWeave] Manual TRX verification initiated (no reference)', [
                'order_id'       => $orderId,
                'trx_id'         => $trxId,
                'amount'         => $expectedAmount,
                'payment_method' => $expectedMethod,
            ]);

            // Step 1 — Call verify without any reference
            $verifyResponse = $this->apiClient->verifyTransaction(
                $orderId,
                $expectedAmount,
                $expectedMethod,
                $trxId,
                null  // no reference
            );

            $verifyStatus = $verifyResponse['status'] ?? 'unknown';

            Log::info('[VendWeave] Manual TRX verify response', [
                'order_id' => $orderId,
                'trx_id'   => $trxId,
                'status'   => $verifyStatus,
            ]);

            // Step 2 — If verify returned pending, fall back to poll without reference
            if ($verifyStatus === 'pending') {
                $pollResponse = $this->apiClient->pollTransaction(
                    $orderId,
                    $expectedAmount,
                    $expectedMethod,
                    $trxId,
                    null  // no reference
                );

                $pollStatus = $pollResponse['status'] ?? 'unknown';

                if (in_array($pollStatus, ['verified', 'confirmed'])) {
                    $verifyResponse = $pollResponse;
                    $verifyStatus   = $pollStatus;
                } elseif ($pollStatus === 'pending') {
                    return VerificationResult::pending('Transaction is still pending. Please wait and try again.');
                } else {
                    return $this->processManualResponse($pollResponse, $expectedAmount, $expectedMethod, $orderId);
                }
            }

            // Step 3 — If verified / confirmed, call confirm-transaction
            if (in_array($verifyStatus, ['verified', 'confirmed'])) {
                $resolvedReference = $this->resolveReferenceForConfirm($verifyResponse, null);

                Log::info('[VendWeave] Manual TRX: calling confirm-transaction', [
                    'order_id'  => $orderId,
                    'trx_id'    => $trxId,
                    'reference' => $resolvedReference,
                ]);

                $confirmResponse = $this->apiClient->confirmTransaction(
                    $trxId,
                    $resolvedReference,
                    $orderId
                );

                // Enrich context
                if (!isset($confirmResponse['trx_id']))    { $confirmResponse['trx_id']    = $trxId; }
                if (!isset($confirmResponse['order_id']))  { $confirmResponse['order_id']  = $orderId; }

                return $this->processManualResponse($confirmResponse, $expectedAmount, $expectedMethod, $orderId);
            }

            // All other statuses (failed, expired, used …)
            return $this->processManualResponse($verifyResponse, $expectedAmount, $expectedMethod, $orderId);

        } catch (ApiConnectionException $e) {
            return VerificationResult::failed('API_ERROR', $e->getMessage());
        }
    }

    /**
     * Process API response for manual (reference-less) verification.
     * Only validates: store, amount, payment method — no reference checks.
     *
     * @param array  $response
     * @param float  $expectedAmount
     * @param string $expectedMethod
     * @param string $orderId
     * @return VerificationResult
     */
    private function processManualResponse(
        array $response,
        float $expectedAmount,
        string $expectedMethod,
        string $orderId
    ): VerificationResult {
        $httpStatus = $response['_http_status'] ?? 200;

        if ($httpStatus === 404) {
            return VerificationResult::failed(
                'TRANSACTION_NOT_FOUND',
                'No matching transaction found for the provided Transaction ID.'
            );
        }

        $status         = $response['status'] ?? 'unknown';
        $trxId          = $response['trx_id'] ?? null;
        $receivedAmount = (float) ($response['amount'] ?? 0);
        $receivedMethod = strtolower($response['payment_method'] ?? '');
        $receivedStore  = $response['store_slug'] ?? null;
        $expectedStore  = $this->apiClient->getStoreSlug();

        switch ($status) {
            case 'pending':
                return VerificationResult::pending('Transaction is still pending. Please wait and try again.');

            case 'expired':
                return VerificationResult::expired($trxId ?? 'unknown');

            case 'used':
                // If it belongs to THIS order, allow idempotent success
                if ($receivedStore === $expectedStore || $expectedStore === null) {
                    if ((string) ($response['order_id'] ?? '') === (string) $orderId) {
                        return VerificationResult::confirmed(
                            $trxId,
                            $receivedAmount ?: $expectedAmount,
                            $receivedMethod ?: $expectedMethod,
                            $receivedStore ?? $expectedStore
                        );
                    }
                }
                return VerificationResult::alreadyUsed($trxId ?? 'unknown');

            case 'failed':
                return VerificationResult::failed(
                    'TRANSACTION_FAILED',
                    $response['message'] ?? 'Transaction verification failed.'
                );

            case 'confirmed':
                // 1. Store check
                if ($expectedStore !== null && $receivedStore !== null && $receivedStore !== $expectedStore) {
                    Log::warning('[VendWeave] Manual TRX: store mismatch', [
                        'expected' => $expectedStore,
                        'received' => $receivedStore,
                        'trx_id'   => $trxId,
                    ]);
                    return VerificationResult::failed(
                        'STORE_MISMATCH',
                        "Transaction belongs to a different store ({$receivedStore})."
                    );
                }

                // 2. Amount check (strict — no tolerance)
                if (!$this->amountsMatch($expectedAmount, $receivedAmount)) {
                    Log::warning('[VendWeave] Manual TRX: amount mismatch', [
                        'expected' => $expectedAmount,
                        'received' => $receivedAmount,
                        'trx_id'   => $trxId,
                    ]);
                    return VerificationResult::failed(
                        'AMOUNT_MISMATCH',
                        "Amount mismatch: expected {$expectedAmount}, received {$receivedAmount}."
                    );
                }

                // 3. Payment method check
                if (!empty($receivedMethod) && strtolower($expectedMethod) !== $receivedMethod) {
                    Log::warning('[VendWeave] Manual TRX: payment method mismatch', [
                        'expected' => $expectedMethod,
                        'received' => $receivedMethod,
                        'trx_id'   => $trxId,
                    ]);
                    return VerificationResult::failed(
                        'METHOD_MISMATCH',
                        "Payment method mismatch: expected {$expectedMethod}, received {$receivedMethod}."
                    );
                }

                // 4. Financial record (if available)
                if (class_exists(FinancialRecordManager::class) && FinancialRecordManager::isAvailable()) {
                    FinancialRecordManager::createFromReference(
                        $trxId ?? $orderId,
                        $orderId,
                        $receivedStore ?? $expectedStore,
                        $expectedAmount,
                        $receivedAmount,
                        $receivedMethod ?: $expectedMethod,
                        $trxId,
                        array_merge($response, [
                            'currency'      => $response['currency'] ?? config('vendweave.base_currency', 'USD'),
                            'base_currency' => config('vendweave.base_currency', 'USD'),
                            'manual_verify' => true,
                        ])
                    );
                }

                Log::info('[VendWeave] Manual TRX verification successful', [
                    'order_id' => $orderId,
                    'trx_id'   => $trxId,
                    'amount'   => $receivedAmount,
                    'method'   => $receivedMethod,
                ]);

                return VerificationResult::confirmed(
                    $trxId,
                    $receivedAmount,
                    $receivedMethod ?: $expectedMethod,
                    $receivedStore ?? $expectedStore,
                    'skipped'   // reference_status: skipped for manual mode
                );

            default:
                Log::warning('[VendWeave] Manual TRX: unrecognized status', [
                    'status'   => $status,
                    'order_id' => $orderId,
                ]);
                return VerificationResult::pending('Transaction status is unknown. Please try again.');
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
                if ($this->isUsedForCurrentOrder($response, $orderId, $expectedReference)) {
                    // Payment already confirmed for THIS order - return success directly
                    // Skip governance checks - this is an idempotent retry (page refresh, etc.)
                    Log::info('[VendWeave] Used status matched current order - returning confirmed (idempotent)', [
                        'order_id' => $orderId,
                        'reference' => $expectedReference,
                        'trx_id' => $response['trx_id'] ?? null,
                    ]);
                    
                    // Direct success - no need to re-validate
                    return VerificationResult::confirmed(
                        $response['trx_id'] ?? 'unknown',
                        (float) ($response['amount'] ?? $expectedAmount),
                        $response['payment_method'] ?? $expectedMethod,
                        $response['store_slug'] ?? $this->apiClient->getStoreSlug() ?? 'unknown'
                    );
                }

                return VerificationResult::alreadyUsed($response['trx_id'] ?? 'unknown');

            case 'expired':
                return VerificationResult::expired($response['trx_id'] ?? 'unknown');

            case 'failed':
                if ($this->isRetryablePollingFailure($response)) {
                    return VerificationResult::pending(
                        $response['message'] ?? 'Rate limit reached. Retrying verification...'
                    );
                }

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
     * Treat transient polling failures as retryable to avoid false checkout failures.
     */
    private function isRetryablePollingFailure(array $response): bool
    {
        $httpStatus = (int) ($response['_http_status'] ?? 0);
        $message = strtolower((string) ($response['message'] ?? ''));

        if ($httpStatus !== 429 && !str_contains($message, 'too many requests')) {
            return false;
        }

        return str_contains($message, 'too many requests')
            || str_contains($message, 'wait before retrying')
            || str_contains($message, 'rate limit');
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

        // 2a. If POS says used but this order matches, treat as matched
        if ($status === 'used' && $this->isUsedForCurrentOrder($response, $orderId, $expectedReference)) {
            $posReferenceStatus = 'matched';
            $referenceStatus = 'matched';
            Log::info('[VendWeave] Used status with matching reference/order - treating as matched', array_merge($logContext, [
                'reference_status' => $referenceStatus,
            ]));
        }

        // 2b. Check POS reference_status first (expired/replayed detection)
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

        // 2c. SDK-side reference validation (if POS didn't provide status)
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
        $referenceForGovernance = $receivedReference ?? $expectedReference ?? null;
        if ($referenceForGovernance !== null && class_exists(ReferenceGovernor::class) && ReferenceGovernor::isAvailable()) {
            $governedStatus = ReferenceGovernor::validate(
                $referenceForGovernance,
                $orderId,
                $receivedStoreSlug ?? $expectedStoreSlug
            );

            switch ($governedStatus) {
                case ReferenceGovernor::STATUS_MATCHED:
                    // MATCHED means this exact order already used this reference - idempotent success
                    // This is NOT a replay, it's the same order requesting again (page refresh, etc.)
                    Log::info('[VendWeave] Reference already matched for this order - allowing idempotent verification', [
                        'reference' => $referenceForGovernance,
                        'order_id' => $orderId,
                        'status' => $governedStatus,
                    ]);
                    break; // Allow to proceed - same order, same reference = valid
                    
                case ReferenceGovernor::STATUS_REPLAYED:
                    // REPLAYED means a DIFFERENT order tried to use this reference
                    return VerificationResult::failed(
                        'REFERENCE_REPLAY',
                        "Payment reference {$referenceForGovernance} has already been used"
                    );

                case ReferenceGovernor::STATUS_EXPIRED:
                    return VerificationResult::failed(
                        'REFERENCE_EXPIRED',
                        "Payment reference {$referenceForGovernance} has expired"
                    );

                case ReferenceGovernor::STATUS_CANCELLED:
                    return VerificationResult::failed(
                        'REFERENCE_CANCELLED',
                        "Payment reference {$referenceForGovernance} was cancelled"
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
     * Determine if a USED status belongs to the current order/reference.
     */
    private function isUsedForCurrentOrder(array $response, string $orderId, ?string $expectedReference): bool
    {
        $receivedOrderId = $response['order_id'] ?? null;
        $receivedReference = $response['reference'] ?? null;

        if ($expectedReference !== null && $receivedReference !== null) {
            return $receivedReference === $expectedReference;
        }

        if ($receivedOrderId !== null) {
            return (string) $receivedOrderId === (string) $orderId;
        }

        return false;
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

    /**
     * Resolve reference to use during confirm.
     */
    private function resolveReferenceForConfirm(array $response, ?string $fallbackReference = null): ?string
    {
        if (!empty($response['payment_reference'])) {
            return (string) $response['payment_reference'];
        }

        if (!empty($response['reference'])) {
            return (string) $response['reference'];
        }

        return $fallbackReference;
    }

    /**
     * Determine whether verify response indicates reference-only failure and
     * should be retried without reference (trx fallback mode).
     */
    private function shouldAttemptReferenceFallback(array $response): bool
    {
        $errorCode = strtoupper((string) ($response['error_code'] ?? ''));
        $referenceStatus = strtolower((string) ($response['reference_status'] ?? ''));

        return in_array($errorCode, ['REFERENCE_MISMATCH', 'REFERENCE_MISSING'], true)
            || in_array($referenceStatus, ['mismatched', 'missing'], true);
    }
}
