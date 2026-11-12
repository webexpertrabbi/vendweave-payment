<?php

namespace VendWeave\Gateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use VendWeave\Gateway\Services\PaymentManager;
use VendWeave\Gateway\Services\ReferenceGovernor;
use VendWeave\Gateway\Services\VendWeaveApiClient;
use VendWeave\Gateway\VendWeaveHelper;

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

        $localPolling = [
            'interval_ms' => (int) config('vendweave.polling.interval_ms', 2000),
            'max_attempts' => (int) config('vendweave.polling.max_attempts', 300),
            'timeout_seconds' => (int) config('vendweave.polling.timeout_seconds', 600),
        ];

        $effectivePolling = $localPolling;

        $posPolling = null;
        $pollingMismatch = false;

        try {
            $client = app(VendWeaveApiClient::class);
            $posPolling = $client->getPollingLimits();

            if (is_array($posPolling)) {
                $serverIntervalSeconds = (int) ($posPolling['interval_seconds'] ?? 0);
                $serverMaxRequests = (int) ($posPolling['max_requests'] ?? 0);
                $serverTimeoutSeconds = (int) ($posPolling['timeout_seconds'] ?? 0);

                $pollingMismatch =
                    $serverIntervalSeconds !== (int) round($localPolling['interval_ms'] / 1000)
                    || $serverMaxRequests !== (int) $localPolling['max_attempts']
                    || $serverTimeoutSeconds !== (int) $localPolling['timeout_seconds'];

                // Use POS limits as the runtime source of truth when available.
                if ($serverIntervalSeconds > 0) {
                    $effectivePolling['interval_ms'] = $serverIntervalSeconds * 1000;
                }

                if ($serverMaxRequests > 0) {
                    $effectivePolling['max_attempts'] = $serverMaxRequests;
                }

                if ($serverTimeoutSeconds > 0) {
                    $effectivePolling['timeout_seconds'] = $serverTimeoutSeconds;
                }
            }
        } catch (\Throwable $e) {
            $posPolling = null;
            $pollingMismatch = false;
        }

        $timerSessionKey = "vendweave_timer_{$order}";
        $reservedSessionKey = "vendweave_reserved_{$order}";
        $timeoutDuration = (int) ($effectivePolling['timeout_seconds'] ?? $localPolling['timeout_seconds']);

        if ($request->boolean('retry')) {
            session()->forget([$timerSessionKey, $reservedSessionKey]);
            unset($orderData['reference']);
        } elseif (session()->has($timerSessionKey)) {
            $previousElapsed = now()->diffInSeconds(session($timerSessionKey), true);
            if ($previousElapsed >= $timeoutDuration) {
                // Previous window expired: force a fresh reservation on revisit
                session()->forget([$timerSessionKey, $reservedSessionKey]);
                unset($orderData['reference']);
            }
        }

        // Ensure an active POS reservation exists whenever the payment window opens
        if (empty($orderData['reference']) || !session()->has($reservedSessionKey)) {
            $storeSlug = (string) config('vendweave.store_slug', 'default');
            $reference = !empty($orderData['reference'])
                ? (string) $orderData['reference']
                : VendWeaveHelper::generateReference($storeSlug);

            try {
                for ($attempt = 0; $attempt < 5; $attempt++) {
                    $reserveResponse = $this->paymentManager->reserveReference(
                        $order,
                        (float) $orderData['amount'],
                        (string) $orderData['payment_method'],
                        $reference
                    );

                    $httpStatus = (int) ($reserveResponse['_http_status'] ?? 0);
                    $rawStatus = (string) ($reserveResponse['raw_status'] ?? ($reserveResponse['status'] ?? ''));

                    if (($httpStatus === 200 || $httpStatus === 201) && !empty($reserveResponse['reference'])) {
                        $reference = (string) $reserveResponse['reference'];
                        session([$reservedSessionKey => time()]);
                        break;
                    }

                    if ($httpStatus === 409 || $rawStatus === 'reference_conflict') {
                        $reference = VendWeaveHelper::generateReference($storeSlug);
                        continue;
                    }

                    break;
                }
            } catch (\Throwable $e) {
                Log::warning('[VendWeave] Failed to ensure reservation on verify show', [
                    'order_id' => $order,
                    'error' => $e->getMessage(),
                ]);
            }

            $orderData['reference'] = $reference;
            ReferenceGovernor::reserve($storeSlug, $order, $reference, (int) config('vendweave.reference_ttl', 30));
            session(["vendweave_order_{$order}" => $orderData]);
        }

        // Initialize or retrieve start time for persistent timer
        if (!session()->has($timerSessionKey)) {
            session([$timerSessionKey => now()]);
        }

        $startTime = session($timerSessionKey);
        $elapsed = now()->diffInSeconds($startTime, true);
        $timeRemaining = max(0, $timeoutDuration - $elapsed);

        return view('vendweave::verify', [
            'orderId' => $order,
            'amount' => $orderData['amount'],
            'paymentMethod' => $orderData['payment_method'],
            'paymentMethodInfo' => $this->paymentManager->getPaymentMethodsInfo()[$orderData['payment_method']] ?? null,
            'reference' => $orderData['reference'] ?? null,
            'pollUrl' => route('vendweave.poll', ['order' => $order]),
            'cancelUrl' => route('vendweave.cancelled', ['order' => $order]),
            'pollingInterval' => (int) ($effectivePolling['interval_ms'] ?? $localPolling['interval_ms']),
            'maxAttempts' => (int) ($effectivePolling['max_attempts'] ?? $localPolling['max_attempts']),
            'timeout' => $timeRemaining, // Dynamic remaining time
            'localPolling' => $localPolling,
            'posPolling' => $posPolling,
            'pollingMismatch' => $pollingMismatch,
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
        $orderData = session("vendweave_order_{$order}");
        $reason = (string) $request->query('reason', 'user_cancelled');
        $message = (string) $request->query(
            'message',
            $reason === 'timeout'
                ? 'Payment verification timed out. Please retry with a fresh reference.'
                : 'Payment was cancelled. You can retry or choose another payment method.'
        );

        if (is_array($orderData) && !empty($orderData['reference'])) {
            try {
                app(VendWeaveApiClient::class)->cancelReservation(
                    (string) $orderData['reference'],
                    $order,
                    $reason
                );
            } catch (\Throwable $e) {
                Log::warning('[VendWeave] Failed to cancel POS reservation on cancel/timeout', [
                    'order_id' => $order,
                    'reference' => $orderData['reference'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        VendWeaveHelper::clearOrderData($order, true);

        $retryParams = ['order' => $order, 'retry' => 1];
        if (is_array($orderData) && isset($orderData['amount'], $orderData['payment_method'])) {
            $retryParams['amount'] = $orderData['amount'];
            $retryParams['payment_method'] = $orderData['payment_method'];
        }

        return view('vendweave::error', [
            'orderId' => $order,
            'errorCode' => $reason === 'timeout' ? 'TIMEOUT' : 'CANCELLED',
            'errorMessage' => $message,
            'retryUrl' => route('vendweave.verify', $retryParams),
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
