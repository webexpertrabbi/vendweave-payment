<?php

namespace VendWeave\Gateway\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use VendWeave\Gateway\Services\PaymentManager;
use VendWeave\Gateway\Services\TransactionVerifier;
use VendWeave\Gateway\Services\VerificationResult;
use Illuminate\Support\Facades\Log;

/**
 * Controller for manual TRX-ID-based payment verification.
 *
 * This endpoint is used when a user did NOT include the reference number
 * in their payment. Instead of requiring a reference, it matches the
 * transaction by: TRX ID + amount + payment method + store slug.
 *
 * The auto-polling and reference system remain completely unchanged.
 * This is purely an additive fallback path.
 */
class ManualVerifyController extends BaseController
{
    public function __construct(
        private readonly PaymentManager $paymentManager,
        private readonly TransactionVerifier $transactionVerifier
    ) {}

    /**
     * Manually verify a transaction using TRX ID (no reference required).
     *
     * Request body:
     *   - trx_id         (string, required)  The payment transaction ID
     *   - amount         (float,  required)  Expected payment amount
     *   - payment_method (string, required)  Payment method slug (e.g. "bkash")
     *
     * @param Request $request
     * @param string  $order  Order ID from URL
     * @return JsonResponse
     */
    public function verify(Request $request, string $order): JsonResponse
    {
        // ── Validate inputs ──────────────────────────────────────────────
        $trxId = trim((string) $request->input('trx_id', ''));
        if ($trxId === '') {
            return $this->errorResponse(
                'MISSING_TRX_ID',
                'Transaction ID is required for manual verification.',
                400
            );
        }

        $amountRaw = $request->input('amount');
        if ($amountRaw === null || !is_numeric($amountRaw) || (float) $amountRaw <= 0) {
            return $this->errorResponse(
                'MISSING_AMOUNT',
                'A valid positive amount is required.',
                400
            );
        }

        $paymentMethod = strtolower(trim((string) $request->input('payment_method', '')));
        if ($paymentMethod === '') {
            return $this->errorResponse(
                'MISSING_PAYMENT_METHOD',
                'Payment method is required.',
                400
            );
        }

        if (!$this->paymentManager->isValidPaymentMethod($paymentMethod)) {
            return $this->errorResponse(
                'INVALID_PAYMENT_METHOD',
                "Payment method '{$paymentMethod}' is not supported.",
                400
            );
        }

        $amount = (float) $amountRaw;

        Log::info('[VendWeave] ManualVerifyController: request received', [
            'order_id'       => $order,
            'trx_id'         => $trxId,
            'amount'         => $amount,
            'payment_method' => $paymentMethod,
        ]);

        // ── Perform reference-less verification ───────────────────────────
        $result = $this->transactionVerifier->verifyByTrxId(
            $order,
            $amount,
            $paymentMethod,
            $trxId
        );

        return $this->formatResponse($result, $order);
    }

    /**
     * Format the VerificationResult as a JSON response.
     */
    private function formatResponse(VerificationResult $result, string $orderId): JsonResponse
    {
        $data             = $result->toArray();
        $data['order_id'] = $orderId;

        if ($result->isConfirmed()) {
            $data['redirect_url'] = route('vendweave.success', [
                'order'  => $orderId,
                'trx_id' => $result->getTrxId(),
            ]);
        } elseif ($result->isFailed()) {
            $data['redirect_url'] = route('vendweave.failed', [
                'order'         => $orderId,
                'error_code'    => $result->getErrorCode(),
                'error_message' => $result->getErrorMessage(),
            ]);
        }

        $statusCode = match ($result->getStatus()) {
            VerificationResult::STATUS_CONFIRMED => 200,
            VerificationResult::STATUS_PENDING   => 202,
            VerificationResult::STATUS_USED      => 409,
            VerificationResult::STATUS_EXPIRED   => 410,
            default                              => 400,
        };

        return response()->json($data, $statusCode);
    }

    /**
     * Return a standardised error JSON response.
     */
    private function errorResponse(string $code, string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'error'         => true,
            'error_code'    => $code,
            'error_message' => $message,
            'status'        => 'failed',
        ], $status);
    }
}
