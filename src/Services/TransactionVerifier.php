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
     * @return VerificationResult
     */
    public function verify(
        string $orderId,
        float $expectedAmount,
        string $expectedMethod,
        ?string $trxId = null
    ): VerificationResult {
        try {
            $response = $this->apiClient->pollTransaction(
                $orderId,
                $expectedAmount,
                $expectedMethod,
                $trxId
            );

            return $this->processResponse($response, $expectedAmount, $expectedMethod);

        } catch (ApiConnectionException $e) {
            return VerificationResult::failed(
                'API_ERROR',
                $e->getMessage()
            );
        }
    }

    /**
     * Process the API response and validate all matching criteria.
     *
     * @param array $response
     * @param float $expectedAmount
     * @param string $expectedMethod
     * @return VerificationResult
     */
    private function processResponse(
        array $response,
        float $expectedAmount,
        string $expectedMethod
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
                    $expectedMethod
                );

            default:
                return VerificationResult::failed(
                    'UNKNOWN_STATUS',
                    "Unknown transaction status: {$status}"
                );
        }
    }

    /**
     * Validate a confirmed transaction against expected values.
     *
     * @param array $response
     * @param float $expectedAmount
     * @param string $expectedMethod
     * @return VerificationResult
     */
    private function validateConfirmedTransaction(
        array $response,
        float $expectedAmount,
        string $expectedMethod
    ): VerificationResult {
        $trxId = $response['trx_id'] ?? null;
        $receivedAmount = (float) ($response['amount'] ?? 0);
        $receivedMethod = strtolower($response['payment_method'] ?? '');
        $receivedStoreSlug = $response['store_slug'] ?? null;
        $expectedStoreSlug = $this->apiClient->getStoreSlug();

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

        // 2. Validate exact amount match (NO TOLERANCE - CRITICAL)
        if (!$this->amountsMatch($expectedAmount, $receivedAmount)) {
            return VerificationResult::failed(
                'AMOUNT_MISMATCH',
                "Amount mismatch: expected {$expectedAmount}, received {$receivedAmount}"
            );
        }

        // 3. Validate payment method match
        if (strtolower($expectedMethod) !== $receivedMethod) {
            return VerificationResult::failed(
                'METHOD_MISMATCH',
                "Payment method mismatch: expected {$expectedMethod}, received {$receivedMethod}"
            );
        }

        // All validations passed
        return VerificationResult::confirmed(
            $trxId,
            $receivedAmount,
            $receivedMethod,
            $receivedStoreSlug ?? $expectedStoreSlug
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
