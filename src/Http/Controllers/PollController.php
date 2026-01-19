<?php

namespace VendWeave\Gateway\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use VendWeave\Gateway\Services\PaymentManager;
use VendWeave\Gateway\Services\VerificationResult;

/**
 * Controller for AJAX polling endpoint.
 * 
 * This controller handles transaction status polling from the verify page.
 * It is rate-limited to prevent abuse.
 */
class PollController extends BaseController
{
    public function __construct(
        private readonly PaymentManager $paymentManager
    ) {}

    /**
     * Poll for transaction verification status.
     *
     * @param Request $request
     * @param string $order Order ID
     * @return JsonResponse
     */
    public function poll(Request $request, string $order): JsonResponse
    {
        // Validate request
        $validated = $this->validateRequest($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $amount = (float) $request->input('amount');
        $paymentMethod = strtolower($request->input('payment_method'));
        $trxId = $request->input('trx_id');
        $reference = $request->input('reference');

        // Validate payment method
        if (!$this->paymentManager->isValidPaymentMethod($paymentMethod)) {
            return $this->errorResponse(
                'INVALID_PAYMENT_METHOD',
                'Invalid payment method provided',
                400
            );
        }

        // Verify transaction (with optional reference)
        $result = $this->paymentManager->verify($order, $amount, $paymentMethod, $trxId, $reference);

        return $this->formatResponse($result, $order);
    }

    /**
     * Health check endpoint.
     *
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'vendweave-gateway',
            'version' => '1.0.0',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Validate the poll request.
     *
     * @param Request $request
     * @return JsonResponse|null
     */
    private function validateRequest(Request $request): ?JsonResponse
    {
        if (!$request->has('amount')) {
            return $this->errorResponse(
                'MISSING_AMOUNT',
                'Amount is required',
                400
            );
        }

        if (!$request->has('payment_method')) {
            return $this->errorResponse(
                'MISSING_PAYMENT_METHOD',
                'Payment method is required',
                400
            );
        }

        $amount = $request->input('amount');
        if (!is_numeric($amount) || $amount <= 0) {
            return $this->errorResponse(
                'INVALID_AMOUNT',
                'Amount must be a positive number',
                400
            );
        }

        return null;
    }

    /**
     * Format the verification result as JSON response.
     *
     * @param VerificationResult $result
     * @param string $orderId
     * @return JsonResponse
     */
    private function formatResponse(VerificationResult $result, string $orderId): JsonResponse
    {
        $data = $result->toArray();
        $data['order_id'] = $orderId;

        // Add redirect URLs for frontend
        if ($result->isConfirmed()) {
            $data['redirect_url'] = route('vendweave.success', [
                'order' => $orderId,
                'trx_id' => $result->getTrxId(),
            ]);
        } elseif ($result->isFailed()) {
            $data['redirect_url'] = route('vendweave.failed', [
                'order' => $orderId,
                'error_code' => $result->getErrorCode(),
                'error_message' => $result->getErrorMessage(),
            ]);
        }

        $statusCode = $this->getHttpStatus($result);

        return response()->json($data, $statusCode);
    }

    /**
     * Get appropriate HTTP status code for result.
     *
     * @param VerificationResult $result
     * @return int
     */
    private function getHttpStatus(VerificationResult $result): int
    {
        return match($result->getStatus()) {
            VerificationResult::STATUS_CONFIRMED => 200,
            VerificationResult::STATUS_PENDING => 202,
            VerificationResult::STATUS_USED => 409,
            VerificationResult::STATUS_EXPIRED => 410,
            default => 400,
        };
    }

    /**
     * Return an error response.
     *
     * @param string $code
     * @param string $message
     * @param int $status
     * @return JsonResponse
     */
    private function errorResponse(string $code, string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'error' => true,
            'error_code' => $code,
            'error_message' => $message,
            'status' => 'failed',
        ], $status);
    }
}
