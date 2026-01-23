<?php

namespace VendWeave\Gateway\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use VendWeave\Gateway\Exceptions\ApiConnectionException;
use VendWeave\Gateway\Exceptions\InvalidCredentialsException;

/**
 * HTTP client for VendWeave POS API communication.
 * 
 * This service handles all direct API communication with the POS system.
 * It manages authentication headers, request/response cycles, and error handling.
 * 
 * API Endpoints (Laravel SDK Namespace):
 * - POST /api/sdk/laravel/reserve-reference
 * - POST /api/sdk/laravel/poll
 * - POST /api/sdk/laravel/verify
 * - POST /api/sdk/laravel/confirm
 */
class VendWeaveApiClient
{
    private Client $client;
    private string $endpoint;
    private ?string $apiKey;
    private ?string $apiSecret;
    private ?string $storeSlug;

    public function __construct(
        string $endpoint,
        ?string $apiKey,
        ?string $apiSecret,
        ?string $storeSlug
    ) {
        $this->endpoint = rtrim($endpoint, '/');
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->storeSlug = $storeSlug;

        // SSL verification - can be disabled for local development
        // Try config first, then env directly
        $verifySsl = config('vendweave.verify_ssl');
        if ($verifySsl === null) {
            $envValue = env('VENDWEAVE_VERIFY_SSL', 'true');
            $verifySsl = filter_var($envValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        }
        // Handle string 'false' from config
        if (is_string($verifySsl)) {
            $verifySsl = filter_var($verifySsl, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        }

        $this->client = new Client([
            'base_uri' => $this->endpoint,
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
            'verify' => $verifySsl,
        ]);
    }

    /**
     * Poll for transaction status from POS.
     * This is the primary polling endpoint for verification page.
     *
     * @param string $orderId
     * @param float $amount
     * @param string $paymentMethod
     * @param string|null $trxId
     * @param string|null $reference Payment reference for matching
     * @return array Raw API response data
     * @throws ApiConnectionException
     * @throws InvalidCredentialsException
     */
    public function pollTransaction(
        string $orderId,
        float $amount,
        string $paymentMethod,
        ?string $trxId = null,
        ?string $reference = null
    ): array {
        $this->validateCredentials();

        // Use intelligent parameter mapping (two-layer system)
        $params = $this->normalizeApiPayload([
            'store_slug' => $this->storeSlug,
            'order_id' => $orderId,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
        ]);

        // Add optional parameters
        if ($trxId !== null) {
            $params['trx_id'] = $trxId;
        }
        if ($reference !== null) {
            $params['reference'] = $reference;
        }

        // Reference-based logging (before request for debugging)
        $this->log('info', 'Poll Transaction', [
            'endpoint' => '/api/sdk/laravel/poll',
            'reference' => $reference,
            'payment_reference' => $reference,
            'order_id' => $orderId,
            'amount' => $amount,
            'store_slug' => $this->storeSlug,
        ]);

        $response = $this->request('POST', '/api/sdk/laravel/poll', $params);
        
        // DEBUG LOGGING START
        $this->log('info', 'Poll Transaction Debug', [
            'order_id' => $orderId,
            'expected_method' => $paymentMethod,
            'raw_response_payment_method' => $response['payment_method'] ?? 'MISSING',
            'raw_response' => $response
        ]);
        // DEBUG LOGGING END

        // Normalize response structure (List → Object, auto-detect fields)
        $normalized = $this->normalizeResponse($response);
        
        // Inject payment_method if API didn't return it
        if (empty($normalized['payment_method'])) {
            $this->log('warning', 'API response missing payment_method, injecting expected value', [
                'expected_payment_method' => $paymentMethod,
                'normalized_before' => $normalized
            ]);
            $normalized['payment_method'] = $paymentMethod;
        } else {
             $this->log('info', 'Payment method found in API response', [
                'method' => $normalized['payment_method']
            ]);
        }
        
        return $normalized;
    }

    /**
     * Verify a transaction against the POS API.
     * Used for final transaction verification with TRX ID.
     *
     * @param string $orderId
     * @param float $amount
     * @param string $paymentMethod
     * @param string $trxId
     * @param string|null $reference Payment reference for matching
     * @return array Raw API response data
     * @throws ApiConnectionException
     * @throws InvalidCredentialsException
     */
    public function verifyTransaction(
        string $orderId,
        float $amount,
        string $paymentMethod,
        string $trxId,
        ?string $reference = null
    ): array {
        $this->validateCredentials();

        // Use intelligent parameter mapping (two-layer system)
        $params = $this->normalizeApiPayload([
            'store_slug' => $this->storeSlug,
            'order_id' => $orderId,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'trx_id' => $trxId,
        ]);

        // Add reference if provided
        if ($reference !== null) {
            $params['reference'] = $reference;
        }

        $this->log('info', 'Verify Transaction', [
            'endpoint' => '/api/sdk/laravel/verify',
            'reference' => $reference,
            'payment_reference' => $reference,
            'order_id' => $orderId,
            'amount' => $amount,
            'trx_id' => $trxId,
            'store_slug' => $this->storeSlug,
        ]);

        $response = $this->request('POST', '/api/sdk/laravel/verify', $params);
        
        // Normalize response structure (List → Object, auto-detect fields)
        $normalized = $this->normalizeResponse($response);
        
        // Inject payment_method if API didn't return it
        if (empty($normalized['payment_method'])) {
            $this->log('warning', 'API response missing payment_method, injecting expected value', [
                'expected_payment_method' => $paymentMethod,
            ]);
            $normalized['payment_method'] = $paymentMethod;
        }
        
        return $normalized;
    }

    /**
     * Confirm a verified transaction with the POS API.
     * Used to consume/lock the transaction after verify returns confirmed.
     *
     * @param string $trxId
     * @param string|null $reference Payment reference for matching
     * @return array Raw API response data
     * @throws ApiConnectionException
     * @throws InvalidCredentialsException
     */
    public function confirmTransaction(string $trxId, ?string $reference = null): array
    {
        $this->validateCredentials();

        $params = $this->normalizeApiPayload([
            'trx_id' => $trxId,
        ]);

        if ($reference !== null) {
            $params['reference'] = $reference;
        }

        $this->log('info', 'Confirm Transaction', [
            'endpoint' => '/api/sdk/laravel/confirm',
            'reference' => $reference,
            'payment_reference' => $reference,
            'trx_id' => $trxId,
            'store_slug' => $this->storeSlug,
        ]);

        $response = $this->request('POST', '/api/sdk/laravel/confirm', $params);

        return $this->normalizeResponse($response);
    }

    /**
     * Reserve a payment reference with the POS API.
     * 
     * @param string $orderId
     * @param float $amount
     * @param string $paymentMethod
     * @param string $reference
     * @return array Raw API response data
     * @throws ApiConnectionException
     * @throws InvalidCredentialsException
     */
    public function reserveReference(
        string $orderId,
        float $amount,
        string $paymentMethod,
        string $reference
    ): array {
        $this->validateCredentials();

        $params = $this->normalizeApiPayload([
            'order_id' => $orderId,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'reference' => $reference,
        ]);

        $this->log('info', 'Reserve Reference', [
            'endpoint' => '/api/sdk/laravel/reserve-reference',
            'reference' => $reference,
            'payment_reference' => $reference,
            'order_id' => $orderId,
            'amount' => $amount,
            'store_slug' => $this->storeSlug,
        ]);

        $response = $this->request('POST', '/api/sdk/laravel/reserve-reference', $params);

        return $this->normalizeResponse($response);
    }

    /**
     * Get the configured store slug.
     */
    public function getStoreSlug(): ?string
    {
        return $this->storeSlug;
    }

    /**
     * Make an authenticated request to the POS API.
     *
     * @param string $method HTTP method
     * @param string $path API path
     * @param array $params Query parameters or body data
     * @return array Decoded response data
     * @throws ApiConnectionException
     * @throws InvalidCredentialsException
     */
    private function request(string $method, string $path, array $params = []): array
    {
        $options = [
            'headers' => $this->getAuthHeaders(),
        ];

        if ($method === 'GET') {
            $options['query'] = $params;
        } else {
            $options['json'] = $params;
        }

        $this->log('info', 'VendWeave API Request', [
            'method' => $method,
            'path' => $path,
            'store_slug' => $this->storeSlug,
            'params' => $this->sanitizeForLog($params),
        ]);

        try {
            $response = $this->client->request($method, $path, $options);
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true) ?? [];

            $this->log('info', 'VendWeave API Response', [
                'status_code' => $statusCode,
                'response' => $this->sanitizeForLog($data),
            ]);

            if ($statusCode === 401) {
                throw new InvalidCredentialsException('API authentication failed');
            }

            if ($statusCode >= 500) {
                throw new ApiConnectionException(
                    'POS API returned server error: ' . ($data['message'] ?? 'Unknown error')
                );
            }

            return array_merge($data, ['_http_status' => $statusCode]);

        } catch (ConnectException $e) {
            $this->log('error', 'VendWeave API Connection Failed', [
                'error' => $e->getMessage(),
            ]);
            throw new ApiConnectionException(
                'Unable to connect to VendWeave POS API: ' . $e->getMessage(),
                $e
            );
        } catch (RequestException $e) {
            $this->log('error', 'VendWeave API Request Failed', [
                'error' => $e->getMessage(),
            ]);
            throw new ApiConnectionException(
                'API request failed: ' . $e->getMessage(),
                $e
            );
        }
    }

    /**
     * Get authentication headers for API requests.
     */
    private function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'X-Store-Secret' => $this->apiSecret,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Validate that API credentials are configured.
     *
     * @throws InvalidCredentialsException
     */
    private function validateCredentials(): void
    {
        if (empty($this->apiKey)) {
            throw new InvalidCredentialsException('VENDWEAVE_API_KEY is not configured');
        }

        if (empty($this->apiSecret)) {
            throw new InvalidCredentialsException('VENDWEAVE_API_SECRET is not configured');
        }

        if (empty($this->storeSlug)) {
            throw new InvalidCredentialsException('VENDWEAVE_STORE_SLUG is not configured');
        }
    }

    /**
     * Log API interactions if logging is enabled.
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!config('vendweave.logging.enabled', true)) {
            return;
        }

        $channel = config('vendweave.logging.channel', 'stack');

        Log::channel($channel)->$level("[VendWeave] {$message}", $context);
    }

    /**
     * Sanitize sensitive data for logging.
     */
    private function sanitizeForLog(array $data): array
    {
        $sensitive = ['api_key', 'api_secret', 'secret', 'password'];

        return array_map(function ($value, $key) use ($sensitive) {
            if (is_string($key) && in_array(strtolower($key), $sensitive)) {
                return '***REDACTED***';
            }
            return $value;
        }, $data, array_keys($data));
    }

    /**
     * Normalize API payload using two-layer parameter mapping.
     * 
     * Layer 1: Config-based mapping (api_param_mapping)
     * Layer 2: Auto-detection fallback (backward compatibility)
     * 
     * This ensures the SDK adapts to POS API contract without forcing
     * users to change their code.
     *
     * @param array $payload
     * @return array Normalized payload with POS API expected field names
     */
    private function normalizeApiPayload(array $payload): array
    {
        $mapping = config('vendweave.api_param_mapping', [
            'order_id' => 'wc_order_id',
            'amount' => 'expected_amount',
        ]);

        $normalized = [];

        foreach ($payload as $key => $value) {
            // Layer 1: Apply configured mapping
            if (isset($mapping[$key])) {
                $normalized[$mapping[$key]] = $value;
                $this->log('debug', 'API param mapped', [
                    'from' => $key,
                    'to' => $mapping[$key],
                    'value' => $this->sanitizeValue($key, $value),
                ]);
            } else {
                $normalized[$key] = $value;
            }
        }

        // Layer 2: Auto-detection fallback for backward compatibility
        // If wc_order_id not set but order_id exists, auto-map it
        if (!isset($normalized['wc_order_id']) && isset($normalized['order_id'])) {
            $normalized['wc_order_id'] = $normalized['order_id'];
            $this->log('debug', 'Auto-mapped order_id → wc_order_id');
        }

        // If expected_amount not set but amount exists, auto-map it
        if (!isset($normalized['expected_amount']) && isset($normalized['amount'])) {
            $normalized['expected_amount'] = $normalized['amount'];
            $this->log('debug', 'Auto-mapped amount → expected_amount');
        }

        return $normalized;
    }

    /**
     * Normalize API response structure and field names.
     * 
     * Handles:
     * - List (indexed array) → Object (associative array)
     * - Missing store_slug injection from config
     * - Multiple field name variations (wc_order_id, order_id, etc.)
     * 
     * This makes the SDK resilient to API response structure changes.
     *
     * @param array $response Raw API response
     * @return array Normalized response
     */
    private function normalizeResponse(array $response): array
    {
        // Step 1: Convert List to Object if needed
        if ($this->isListResponse($response)) {
            $this->log('debug', 'Converting List response to Object');
            $response = $this->convertListToObject($response);
        }

        // Step 2: Auto-detect and normalize field names
        $response = $this->normalizeResponseFields($response);

        // Step 2.1: Normalize status values for consistent handling
        if (isset($response['status'])) {
            $response['raw_status'] = $response['status'];
            $response['status'] = $this->normalizeStatusValue((string) $response['status']);
        }

        // Step 3: Inject missing store_slug if not present
        if (!isset($response['store_slug']) && $this->storeSlug) {
            $this->log('warning', 'API response missing store_slug, injecting from config', [
                'injected_store_slug' => $this->storeSlug,
            ]);
            $response['store_slug'] = $this->storeSlug;
        }

        return $response;
    }

    /**
     * Check if response is a List (indexed array) vs Object (associative array).
     */
    private function isListResponse(array $response): bool
    {
        if (empty($response)) {
            return false;
        }

        // If all keys are numeric and sequential, it's a List
        $keys = array_keys($response);
        return $keys === array_keys($keys);
    }

    /**
     * Convert List response to Object by taking first element.
     * 
     * Some API endpoints return [{ data }] instead of { data }
     */
    private function convertListToObject(array $response): array
    {
        if (empty($response)) {
            return [];
        }

        // Take first element if it's an array
        $first = reset($response);
        return is_array($first) ? $first : $response;
    }

    /**
     * Normalize response field names using fallback detection.
     * 
     * Example: API may return wc_order_id, order_id, or order_no
     * SDK normalizes all to a consistent structure.
     */
    private function normalizeResponseFields(array $response): array
    {
        $fallbacks = config('vendweave.response_field_fallbacks', [
            'order_id' => ['wc_order_id', 'order_id', 'order_no', 'invoice_id'],
            'amount' => ['expected_amount', 'amount', 'total', 'grand_total'],
            'store_slug' => ['store_slug', 'store_id', 'shop_slug'],
            'reference' => ['payment_reference', 'reference', 'ref'],
            'trx_id' => ['trx_id', 'transaction_id', 'payment_id'],
            'status' => ['status', 'transaction_status', 'payment_status', 'txn_status', 'state'],
        ]);

        $normalized = $response;

        foreach ($fallbacks as $standardField => $variations) {
            // Skip if already present
            if (isset($normalized[$standardField])) {
                continue;
            }

            // Try to find value in variations
            foreach ($variations as $variant) {
                if (isset($response[$variant])) {
                    $normalized[$standardField] = $response[$variant];
                    
                    if ($variant !== $standardField) {
                        $this->log('debug', 'Auto-detected response field', [
                            'found' => $variant,
                            'normalized_to' => $standardField,
                        ]);
                    }
                    break;
                }
            }
        }

        return $normalized;
    }

    /**
     * Normalize POS status values to SDK canonical statuses.
     * 
     * Canonical statuses: confirmed, pending, expired, used, failed
     * All unknown/empty values fallback to 'pending' for graceful handling.
     */
    private function normalizeStatusValue(string $status): string
    {
        $normalized = strtolower(trim($status));

        // Empty or whitespace-only → pending
        if ($normalized === '' || $normalized === 'unknown') {
            $this->log('warning', 'Empty or unknown status received, normalizing to pending', [
                'raw_status' => $status,
            ]);
            return 'pending';
        }

        // Canonical status mapping
        $statusMap = [
            // Confirmed variants
            'confirmed' => 'confirmed',
            'success'   => 'confirmed',
            'paid'      => 'confirmed',
            'completed' => 'confirmed',
            'approved'  => 'confirmed',
            'verified'  => 'confirmed',
            'matched'   => 'confirmed',

            // Pending variants
            'pending'    => 'pending',
            'processing' => 'pending',
            'waiting'    => 'pending',
            'initiated'  => 'pending',
            'in_progress'=> 'pending',
            'inprogress' => 'pending',

            // Expired variants
            'expired'    => 'expired',
            'timeout'    => 'expired',
            'timed_out'  => 'expired',

            // Used/Replayed variants
            'used'       => 'used',
            'replayed'   => 'used',
            'duplicate'  => 'used',
            'already_used' => 'used',

            // Failed variants
            'failed'     => 'failed',
            'error'      => 'failed',
            'rejected'   => 'failed',
            'declined'   => 'failed',
            'cancelled'  => 'failed',
            'canceled'   => 'failed',
        ];

        if (isset($statusMap[$normalized])) {
            return $statusMap[$normalized];
        }

        // Graceful fallback: unknown status → pending (never throw)
        $this->log('warning', 'Unrecognized POS status, normalizing to pending', [
            'raw_status' => $status,
            'normalized' => $normalized,
        ]);

        return 'pending';
    }

    /**
     * Sanitize a single value for logging.
     */
    private function sanitizeValue(string $key, $value)
    {
        $sensitive = ['api_key', 'api_secret', 'secret', 'password', 'trx_id'];
        
        if (in_array(strtolower($key), $sensitive)) {
            return '***REDACTED***';
        }
        
        return $value;
    }
}

