<?php

namespace VendWeave\Gateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use VendWeave\Gateway\Services\PaymentManager;

/**
 * Controller for verification page and result pages.
 * 
 * This controller handles:
 * - Display of the verification page (polling UI)
 * - Success page after confirmed payment
 * - Failed page for verification failures
 * - Cancelled page for user cancellation
 */
class VerifyController extends BaseController
{
    public function __construct(
        private readonly PaymentManager $paymentManager
    ) {}

    /**
     * Show the verification page.
     *
     * @param Request $request
     * @param string $order Order ID
     * @return \Illuminate\View\View
     */
    public function show(Request $request, string $order)
    {
        // Get order data from session or query params
        $orderData = $this->getOrderData($request, $order);

        if (!$orderData) {
            return $this->renderError(
                'Order Not Found',
                'The requested order could not be found or has expired.'
            );
        }

        // Initialize or retrieve start time for persistent timer
        $timerSessionKey = "vendweave_timer_{$order}";
        if (!session()->has($timerSessionKey)) {
             session([$timerSessionKey => now()]);
        }
        $startTime = session($timerSessionKey);
        $timeoutDuration = config('vendweave.polling.timeout_seconds', 300);
        $elapsed = now()->diffInSeconds($startTime);
        $timeRemaining = max(0, $timeoutDuration - $elapsed);

        return view('vendweave::verify', [
            'orderId' => $order,
            'amount' => $orderData['amount'],
            'paymentMethod' => $orderData['payment_method'],
            'paymentMethodInfo' => $this->paymentManager->getPaymentMethodsInfo()[$orderData['payment_method']] ?? null,
            'reference' => $orderData['reference'] ?? null,
            'pollUrl' => route('vendweave.poll', ['order' => $order]),
            'cancelUrl' => route('vendweave.cancelled', ['order' => $order]),
            'pollingInterval' => config('vendweave.polling.interval_ms', 2500),
            'maxAttempts' => config('vendweave.polling.max_attempts', 120),
            'timeout' => $timeRemaining, // Dynamic remaining time
        ]);
    }

    /**
     * Show the success page.
     *
     * @param Request $request
     * @param string $order
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function success(Request $request, string $order)
    {
        // Check if custom success route is configured
        $customRoute = config('vendweave.callbacks.success_route');
        if ($customRoute) {
            return redirect()->route($customRoute, ['order' => $order]);
        }

        $orderData = $this->getOrderData($request, $order);

        return view('vendweave::success', [
            'orderId' => $order,
            'amount' => $orderData['amount'] ?? null,
            'trxId' => $request->query('trx_id'),
            'paymentMethod' => $orderData['payment_method'] ?? null,
        ]);
    }

    /**
     * Show the failed page.
     *
     * @param Request $request
     * @param string $order
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function failed(Request $request, string $order)
    {
        // Check if custom failed route is configured
        $customRoute = config('vendweave.callbacks.failed_route');
        if ($customRoute) {
            return redirect()->route($customRoute, ['order' => $order]);
        }

        return view('vendweave::error', [
            'orderId' => $order,
            'errorCode' => $request->query('error_code', 'VERIFICATION_FAILED'),
            'errorMessage' => $request->query('error_message', 'Payment verification failed. Please try again.'),
            'retryUrl' => route('vendweave.verify', ['order' => $order]),
        ]);
    }

    /**
     * Show the cancelled page.
     *
     * @param Request $request
     * @param string $order
     * @return \Illuminate\View\View
     */
    public function cancelled(Request $request, string $order)
    {
        return view('vendweave::error', [
            'orderId' => $order,
            'errorCode' => 'CANCELLED',
            'errorMessage' => 'Payment was cancelled. You can retry or choose another payment method.',
            'retryUrl' => route('vendweave.verify', ['order' => $order]),
            'isCancelled' => true,
        ]);
    }

    /**
     * Get order data from session or request.
     *
     * @param Request $request
     * @param string $orderId
     * @return array|null
     */
    private function getOrderData(Request $request, string $orderId): ?array
    {
        // Try session first
        $sessionKey = "vendweave_order_{$orderId}";
        if (session()->has($sessionKey)) {
            return session($sessionKey);
        }

        // Try query parameters
        $amount = $request->query('amount');
        $paymentMethod = $request->query('payment_method');

        if ($amount && $paymentMethod) {
            return [
                'amount' => (float) $amount,
                'payment_method' => strtolower($paymentMethod),
            ];
        }

        return null;
    }

    /**
     * Render an error page.
     *
     * @param string $title
     * @param string $message
     * @return \Illuminate\View\View
     */
    private function renderError(string $title, string $message)
    {
        return view('vendweave::error', [
            'errorCode' => 'ERROR',
            'errorMessage' => $message,
            'retryUrl' => null,
        ]);
    }
}
