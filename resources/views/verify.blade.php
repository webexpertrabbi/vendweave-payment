<<<<<<< HEAD
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Payment - Order #{{ $orderId }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg-primary: #0a0f1a;
            --bg-secondary: #111827;
            --bg-card: #1f2937;
            --text-primary: #f9fafb;
            --text-secondary: #9ca3af;
            --text-muted: #6b7280;
            --border: #374151;
            --shadow: 0 4px 24px rgba(0,0,0,0.4);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        html { font-size: 16px; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            line-height: 1.5;
        }
        
        .container {
            width: 100%;
            max-width: 440px;
        }
        
        .card {
            background: var(--bg-secondary);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        
        /* Header */
        .header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }
        
        .payment-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        .header-text h1 {
            font-size: 17px;
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .header-text p {
            font-size: 13px;
            color: var(--text-muted);
        }
        
        /* Amount */
        .amount-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-card);
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 12px;
        }
        
        .amount-label {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .amount-value {
            font-size: 24px;
            font-weight: 700;
        }
        
        .amount-value span {
            font-size: 14px;
            color: var(--text-secondary);
            margin-left: 2px;
        }
        
        /* Reference Box */
        .reference-box {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 12px;
            text-align: center;
        }
        
        .reference-label {
            font-size: 11px;
            font-weight: 600;
            color: #78350f;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        
        .reference-value {
            font-size: 28px;
            font-weight: 800;
            color: #1e293b;
            font-family: 'Courier New', monospace;
            letter-spacing: 4px;
            margin-bottom: 8px;
        }
        
        .reference-copy-btn {
            background: #1e293b;
            color: #fbbf24;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
        }
        
        .reference-copy-btn:active { transform: scale(0.96); }
        .reference-copy-btn.copied { background: #059669; color: white; }
        
        .reference-note {
            font-size: 11px;
            color: #92400e;
            margin-top: 8px;
        }
        
        /* Status */
        .status-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            background: rgba(245, 158, 11, 0.1);
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: 500;
            color: var(--warning);
        }
        
        .status-bar.status-confirmed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .status-bar.status-failed {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* Two Column Grid */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }
        
        /* Input */
        .input-group {
            background: var(--bg-card);
            border-radius: 8px;
            padding: 12px 14px;
        }
        
        .input-group label {
            display: block;
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .input-group input {
            width: 100%;
            background: transparent;
            border: none;
            outline: none;
            color: var(--text-primary);
            font-size: 15px;
            font-family: inherit;
        }
        
        .input-group input::placeholder {
            color: var(--text-muted);
        }
        
        /* Payment Info Box */
        .pay-info {
            background: var(--bg-card);
            border-radius: 8px;
            padding: 12px 14px;
        }
        
        .pay-info-label {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        
        .pay-info-number {
            font-size: 17px;
            font-weight: 700;
            color: var(--primary);
            font-family: monospace;
            letter-spacing: 1px;
        }
        
        .pay-info-note {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 4px;
            line-height: 1.4;
        }
        
        /* Buttons */
        .btn-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }
        
        .btn {
            padding: 14px 18px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s;
            font-family: inherit;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:active { background: var(--primary-dark); }
        
        .btn-secondary {
            background: var(--bg-card);
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }
        
        .btn-secondary:active { background: var(--border); }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Timer */
        .timer {
            text-align: center;
            font-size: 13px;
            color: var(--text-muted);
        }
        
        .timer strong {
            color: var(--warning);
            font-size: 14px;
        }
        
        /* Error */
        .error-box {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 12px;
            font-size: 13px;
            color: var(--danger);
            display: none;
        }

        /* Notice */
        .notice-box {
            background: rgba(245, 158, 11, 0.12);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 12px;
            font-size: 13px;
            color: var(--warning);
        }

        .notice-box strong {
            color: #fbbf24;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 14px;
        }
        
        .footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        /* Desktop Adjustments */
        @media (min-width: 480px) {
            body { padding: 20px; }
            .container { max-width: 460px; }
            .card { padding: 24px; }
            .header { margin-bottom: 20px; padding-bottom: 16px; }
            .payment-icon { width: 52px; height: 52px; font-size: 22px; }
            .header-text h1 { font-size: 18px; }
            .header-text p { font-size: 14px; }
            .amount-value { font-size: 28px; }
            .reference-value { font-size: 32px; }
            .btn { padding: 16px 22px; font-size: 15px; }
        }
        
        /* Extra Small Mobile */
        @media (max-width: 359px) {
            html { font-size: 15px; }
            .card { padding: 16px; }
            .header { gap: 10px; margin-bottom: 12px; }
            .payment-icon { width: 42px; height: 42px; font-size: 18px; }
            .header-text h1 { font-size: 15px; }
            .amount-value { font-size: 22px; }
            .reference-value { font-size: 24px; letter-spacing: 2px; }
            .grid-2 { grid-template-columns: 1fr; }
            .btn-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <!-- Header -->
            <div class="header">
                @if($paymentMethodInfo)
                <div class="payment-icon" style="background: {{ $paymentMethodInfo['color'] ?? 'var(--primary)' }}22;">
                    <span style="color: {{ $paymentMethodInfo['color'] ?? 'var(--primary)' }}">
                        {{ strtoupper(substr($paymentMethod, 0, 1)) }}
                    </span>
                </div>
                <div class="header-text">
                    <h1>Pay with {{ $paymentMethodInfo['name'] ?? ucfirst($paymentMethod) }}</h1>
                    <p>Order #{{ $orderId }}</p>
                </div>
                @else
                <div class="header-text">
                    <h1>Verify Payment</h1>
                    <p>Order #{{ $orderId }}</p>
                </div>
                @endif
            </div>
            
            <!-- Amount -->
            <div class="amount-row">
                <div class="amount-label">Amount to Pay</div>
                <div class="amount-value">৳{{ number_format($amount, 2) }}<span>BDT</span></div>
            </div>
            
            <!-- Reference -->
            @if($reference ?? null)
            <div class="reference-box">
                <div class="reference-label">⚠️ Include This Reference In Your Payment</div>
                <div class="reference-value" id="ref-value">{{ $reference }}</div>
                <button class="reference-copy-btn" id="copy-ref-btn" onclick="copyReference()">📋 Copy Reference</button>
                <div class="reference-note">Send money with this reference in the note/message field</div>
            </div>
            @endif

            @if(($pollingMismatch ?? false) && !empty($posPolling))
            <div class="notice-box">
                <strong>Polling settings mismatch:</strong>
                Your current client settings differ from POS limits and may be rate-limited.
                <div style="margin-top: 6px; font-size: 12px; color: var(--text-secondary);">
                    Local: {{ (int) round(($localPolling['interval_ms'] ?? 0) / 1000) }}s / {{ (int) ($localPolling['max_attempts'] ?? 0) }} requests / {{ (int) ($localPolling['timeout_seconds'] ?? 0) }}s<br>
                    POS: {{ (int) ($posPolling['interval_seconds'] ?? 0) }}s / {{ (int) ($posPolling['max_requests'] ?? 0) }} requests / {{ (int) ($posPolling['timeout_seconds'] ?? 0) }}s
                </div>
            </div>
            @endif
            
            <!-- Status -->
            <div id="status-bar" class="status-bar">
                <div class="spinner"></div>
                <span id="status-text">Waiting for payment...</span>
            </div>
            
            <!-- Error -->
            <div id="error-box" class="error-box"></div>
            
            <!-- Input & Payment Info -->
            <div class="grid-2">
                <div class="input-group">
                    <label for="trx-id">Transaction ID (Optional)</label>
                    <input type="text" id="trx-id" placeholder="Enter your {{ ucfirst($paymentMethod) }} TRX ID" autocomplete="off">
                </div>
                
                @if(isset($paymentMethodInfo['number']))
                <div class="pay-info">
                    <div class="pay-info-label">Send Money To ({{ ucfirst($paymentMethodInfo['type'] ?? 'Personal') }})</div>
                    <div class="pay-info-number">{{ $paymentMethodInfo['number'] }}</div>
                    <div class="pay-info-note">Send money to this {{ ucfirst($paymentMethod) }} Personal Number using Send Money option.</div>
                </div>
                @else
                <div class="pay-info">
                    <div class="pay-info-label">How to Pay</div>
                    <div class="pay-info-note" style="margin-top: 0;">
                        1. Open {{ ucfirst($paymentMethod) }} app<br>
                        2. Send exactly ৳{{ number_format($amount, 2) }}<br>
                        3. Include reference in note
                    </div>
                </div>
                @endif
            </div>
            
            <!-- Buttons -->
            <div class="btn-row">
                <button id="verify-btn" class="btn btn-primary" type="button">Verify Manually</button>
                <button id="cancel-btn" class="btn btn-secondary" type="button">Cancel</button>
            </div>
            
            <!-- Timer -->
            <div class="timer">Time remaining: <strong id="timer-value">5:00</strong></div>
        </div>
        
        <p class="footer">Powered by <a href="https://vendweave.com/" target="_blank" rel="noopener">VendWeave</a></p>
    </div>
    
    <script>
        (function() {
            'use strict';
            
            const config = {
                orderId: @json($orderId),
                amount: @json($amount),
                paymentMethod: @json($paymentMethod),
                reference: @json($reference ?? null),
                pollUrl: @json($pollUrl),
                cancelUrl: @json($cancelUrl),
                pollingInterval: {{ $pollingInterval }},
                maxAttempts: {{ $maxAttempts }},
                timeoutSeconds: {{ $timeout }} // Remaining time from server session
            };
            
            let pollCount = 0;
            let timeRemaining = config.timeoutSeconds; // Start at remaining time
            let pollTimer = null;
            let countdownTimer = null;
            let isPolling = false;
            let pageLoadTime = Date.now();
            
            const statusBar = document.getElementById('status-bar');
            const statusText = document.getElementById('status-text');
            const errorBox = document.getElementById('error-box');
            const trxInput = document.getElementById('trx-id');
            const verifyBtn = document.getElementById('verify-btn');
            const cancelBtn = document.getElementById('cancel-btn');
            const timerValue = document.getElementById('timer-value');
            
            // Initialize timer display immediately
            updateTimerDisplay();
            
            function startPolling() {
                if (isPolling) return;
                isPolling = true;
                pollTimer = setInterval(poll, config.pollingInterval);
                countdownTimer = setInterval(updateCountdown, 1000);
                poll();
            }
            
            function stopPolling() {
                isPolling = false;
                clearInterval(pollTimer);
                clearInterval(countdownTimer);
            }
            
            async function poll() {
                pollCount++;
                if (pollCount > config.maxAttempts) {
                    handleTimeout();
                    return;
                }
                if (timeRemaining <= 0) { handleTimeout(); return; }
                
                try {
                    const params = new URLSearchParams({
                        amount: config.amount,
                        payment_method: config.paymentMethod
                    });
                    
                    if (config.reference) params.append('reference', config.reference);
                    const trxId = trxInput.value.trim();
                    if (trxId) params.append('trx_id', trxId);
                    
                    const response = await fetch(`${config.pollUrl}?${params}`, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    
                    const data = await response.json();
                    handleResponse(data);
                } catch (error) {
                    console.error('Poll error:', error);
                    showError('Connection error. Retrying...');
                }
            }
            
            function handleResponse(data) {
                hideError();
                switch (data.status) {
                    case 'confirmed':
                    case 'used':
                    case 'success':
                        handleSuccess(data);
                        break;
                    case 'verified':
                        updateStatus('pending', 'Confirming transaction...');
                        break;
                    case 'pending':
                        updateStatus('pending', 'Waiting for payment...');
                        break;
                    case 'failed':
                        handleError(data.error_code || 'FAILED', data.error_message || 'Payment failed');
                        break;
                    case 'expired':
                        handleError('EXPIRED', 'Transaction has expired');
                        break;
                }
            }
            
            function handleSuccess(data) {
                stopPolling();
                updateStatus('confirmed', 'Payment Verified!');
                verifyBtn.disabled = true;
                trxInput.disabled = true;
                setTimeout(() => { if (data.redirect_url) window.location.href = data.redirect_url; }, 1200);
            }
            
            function handleError(code, message) {
                stopPolling();
                updateStatus('failed', 'Verification Failed');
                showError(message);
                verifyBtn.textContent = 'Retry';
                verifyBtn.disabled = false;
            }
            
            function handleTimeout() {
                stopPolling();
                updateStatus('failed', 'Payment Timed Out');
                
                // Build fail URL with reason
                const failReason = encodeURIComponent('Payment verification timed out after 10 minutes. No payment was received within the allowed time.');
                const failUrl = config.cancelUrl + (config.cancelUrl.includes('?') ? '&' : '?') + 
                    'status=failed&reason=timeout&message=' + failReason;
                
                showError('Payment verification timed out. Redirecting...');
                
                setTimeout(() => {
                    window.location.href = failUrl;
                }, 1500);
            }
            
            function updateStatus(status, text) {
                statusBar.className = 'status-bar status-' + status;
                statusText.textContent = text;
                
                if (status === 'pending') {
                    statusBar.innerHTML = '<div class="spinner"></div><span>' + text + '</span>';
                } else if (status === 'confirmed') {
                    statusBar.innerHTML = '<span style="font-size:18px">✓</span><span>' + text + '</span>';
                } else {
                    statusBar.innerHTML = '<span style="font-size:18px">✕</span><span>' + text + '</span>';
                }
            }
            
            function updateCountdown() {
                timeRemaining--;
                updateTimerDisplay();
                
                if (timeRemaining <= 0) {
                    handleTimeout();
                }
            }
            
            function updateTimerDisplay() {
                const m = Math.floor(Math.max(0, timeRemaining) / 60);
                const s = Math.max(0, timeRemaining) % 60;
                timerValue.textContent = `${m}:${s.toString().padStart(2, '0')}`;
                
                // Change color when time is running low
                if (timeRemaining <= 60) {
                    timerValue.style.color = 'var(--danger)';
                } else if (timeRemaining <= 120) {
                    timerValue.style.color = 'var(--warning)';
                }
            }
            
            function showError(msg) { errorBox.textContent = msg; errorBox.style.display = 'block'; }
            function hideError() { errorBox.style.display = 'none'; }
            
            verifyBtn.addEventListener('click', function() {
                if (this.textContent === 'Retry') {
                    // Reset only poll count, not timer (timer continues from page load)
                    pollCount = 0;
                    this.textContent = 'Verify Manually';
                    hideError();
                    updateStatus('pending', 'Verifying payment...');
                    startPolling();
                } else {
                    poll();
                }
            });
            
            cancelBtn.addEventListener('click', function() {
                stopPolling();
                const cancelReason = encodeURIComponent('Payment was cancelled by the user.');
                const cancelUrl = config.cancelUrl + (config.cancelUrl.includes('?') ? '&' : '?') + 
                    'status=cancelled&reason=user_cancelled&message=' + cancelReason;
                window.location.href = cancelUrl;
            });
            
            trxInput.addEventListener('keypress', function(e) { if (e.key === 'Enter') poll(); });
            
            // Handle page visibility - timer keeps running even when minimized
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    // Page is now visible - recalculate time based on page load
                    const elapsedSeconds = Math.floor((Date.now() - pageLoadTime) / 1000);
                    timeRemaining = Math.max(0, config.timeoutSeconds - elapsedSeconds);
                    updateTimerDisplay();
                    
                    if (timeRemaining <= 0) {
                        handleTimeout();
                    }
                }
            });
            
            startPolling();
        })();
        
        function copyReference() {
            const refValue = document.getElementById('ref-value');
            const copyBtn = document.getElementById('copy-ref-btn');
            
            if (refValue && navigator.clipboard) {
                navigator.clipboard.writeText(refValue.innerText).then(function() {
                    copyBtn.classList.add('copied');
                    copyBtn.innerHTML = '✓ Copied!';
                    setTimeout(() => { copyBtn.classList.remove('copied'); copyBtn.innerHTML = '📋 Copy Reference'; }, 2000);
                }).catch(function() { fallbackCopy(refValue.innerText); });
            } else {
                fallbackCopy(refValue ? refValue.innerText : '');
            }
        }
        
        function fallbackCopy(text) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;left:-9999px';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            const copyBtn = document.getElementById('copy-ref-btn');
            if (copyBtn) {
                copyBtn.classList.add('copied');
                copyBtn.innerHTML = '✓ Copied!';
                setTimeout(() => { copyBtn.classList.remove('copied'); copyBtn.innerHTML = '📋 Copy Reference'; }, 2000);
            }
        }
    </script>
</body>
</html>
=======
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Payment - Order #{{ $orderId }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg-primary: #0a0f1a;
            --bg-secondary: #111827;
            --bg-card: #1f2937;
            --text-primary: #f9fafb;
            --text-secondary: #9ca3af;
            --text-muted: #6b7280;
            --border: #374151;
            --shadow: 0 4px 24px rgba(0,0,0,0.4);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        html { font-size: 16px; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            line-height: 1.5;
        }
        
        .container {
            width: 100%;
            max-width: 440px;
        }
        
        .card {
            background: var(--bg-secondary);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        
        /* Header */
        .header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }
        
        .payment-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        .header-text h1 {
            font-size: 17px;
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .header-text p {
            font-size: 13px;
            color: var(--text-muted);
        }
        
        /* Amount */
        .amount-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-card);
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 12px;
        }
        
        .amount-label {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .amount-value {
            font-size: 24px;
            font-weight: 700;
        }
        
        .amount-value span {
            font-size: 14px;
            color: var(--text-secondary);
            margin-left: 2px;
        }
        
        /* Reference Box */
        .reference-box {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 12px;
            text-align: center;
        }
        
        .reference-label {
            font-size: 11px;
            font-weight: 600;
            color: #78350f;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        
        .reference-value {
            font-size: 28px;
            font-weight: 800;
            color: #1e293b;
            font-family: 'Courier New', monospace;
            letter-spacing: 4px;
            margin-bottom: 8px;
        }
        
        .reference-copy-btn {
            background: #1e293b;
            color: #fbbf24;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
        }
        
        .reference-copy-btn:active { transform: scale(0.96); }
        .reference-copy-btn.copied { background: #059669; color: white; }
        
        .reference-note {
            font-size: 11px;
            color: #92400e;
            margin-top: 8px;
        }
        
        /* Status */
        .status-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            background: rgba(245, 158, 11, 0.1);
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: 500;
            color: var(--warning);
        }
        
        .status-bar.status-confirmed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .status-bar.status-failed {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* Two Column Grid */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }
        
        /* Input */
        .input-group {
            background: var(--bg-card);
            border-radius: 8px;
            padding: 12px 14px;
        }
        
        .input-group label {
            display: block;
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .input-group input {
            width: 100%;
            background: transparent;
            border: none;
            outline: none;
            color: var(--text-primary);
            font-size: 15px;
            font-family: inherit;
        }
        
        .input-group input::placeholder {
            color: var(--text-muted);
        }
        
        /* Payment Info Box */
        .pay-info {
            background: var(--bg-card);
            border-radius: 8px;
            padding: 12px 14px;
        }
        
        .pay-info-label {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        
        .pay-info-number {
            font-size: 17px;
            font-weight: 700;
            color: var(--primary);
            font-family: monospace;
            letter-spacing: 1px;
        }
        
        .pay-info-note {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 4px;
            line-height: 1.4;
        }
        
        /* Buttons */
        .btn-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }
        
        .btn {
            padding: 14px 18px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s;
            font-family: inherit;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:active { background: var(--primary-dark); }
        
        .btn-secondary {
            background: var(--bg-card);
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }
        
        .btn-secondary:active { background: var(--border); }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Timer */
        .timer {
            text-align: center;
            font-size: 13px;
            color: var(--text-muted);
        }
        
        .timer strong {
            color: var(--warning);
            font-size: 14px;
        }
        
        /* Error */
        .error-box {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 12px;
            font-size: 13px;
            color: var(--danger);
            display: none;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 14px;
        }
        
        .footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        /* Desktop Adjustments */
        @media (min-width: 480px) {
            body { padding: 20px; }
            .container { max-width: 460px; }
            .card { padding: 24px; }
            .header { margin-bottom: 20px; padding-bottom: 16px; }
            .payment-icon { width: 52px; height: 52px; font-size: 22px; }
            .header-text h1 { font-size: 18px; }
            .header-text p { font-size: 14px; }
            .amount-value { font-size: 28px; }
            .reference-value { font-size: 32px; }
            .btn { padding: 16px 22px; font-size: 15px; }
        }
        
        /* Extra Small Mobile */
        @media (max-width: 359px) {
            html { font-size: 15px; }
            .card { padding: 16px; }
            .header { gap: 10px; margin-bottom: 12px; }
            .payment-icon { width: 42px; height: 42px; font-size: 18px; }
            .header-text h1 { font-size: 15px; }
            .amount-value { font-size: 22px; }
            .reference-value { font-size: 24px; letter-spacing: 2px; }
            .grid-2 { grid-template-columns: 1fr; }
            .btn-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <!-- Header -->
            <div class="header">
                @if($paymentMethodInfo)
                <div class="payment-icon" style="background: {{ $paymentMethodInfo['color'] ?? 'var(--primary)' }}22;">
                    <span style="color: {{ $paymentMethodInfo['color'] ?? 'var(--primary)' }}">
                        {{ strtoupper(substr($paymentMethod, 0, 1)) }}
                    </span>
                </div>
                <div class="header-text">
                    <h1>Pay with {{ $paymentMethodInfo['name'] ?? ucfirst($paymentMethod) }}</h1>
                    <p>Order #{{ $orderId }}</p>
                </div>
                @else
                <div class="header-text">
                    <h1>Verify Payment</h1>
                    <p>Order #{{ $orderId }}</p>
                </div>
                @endif
            </div>
            
            <!-- Amount -->
            <div class="amount-row">
                <div class="amount-label">Amount to Pay</div>
                <div class="amount-value">৳{{ number_format($amount, 2) }}<span>BDT</span></div>
            </div>
            
            <!-- Reference -->
            @if($reference ?? null)
            <div class="reference-box">
                <div class="reference-label">⚠️ Include This Reference In Your Payment</div>
                <div class="reference-value" id="ref-value">{{ $reference }}</div>
                <button class="reference-copy-btn" id="copy-ref-btn" onclick="copyReference()">📋 Copy Reference</button>
                <div class="reference-note">Send money with this reference in the note/message field</div>
            </div>
            @endif
            
            <!-- Status -->
            <div id="status-bar" class="status-bar">
                <div class="spinner"></div>
                <span id="status-text">Waiting for payment...</span>
            </div>
            
            <!-- Error -->
            <div id="error-box" class="error-box"></div>
            
            <!-- Input & Payment Info -->
            <div class="grid-2">
                <div class="input-group">
                    <label for="trx-id">Transaction ID (Optional)</label>
                    <input type="text" id="trx-id" placeholder="Enter your {{ ucfirst($paymentMethod) }} TRX ID" autocomplete="off">
                </div>
                
                @if(isset($paymentMethodInfo['number']))
                <div class="pay-info">
                    <div class="pay-info-label">Send Money To ({{ ucfirst($paymentMethodInfo['type'] ?? 'Personal') }})</div>
                    <div class="pay-info-number">{{ $paymentMethodInfo['number'] }}</div>
                    <div class="pay-info-note">Send money to this {{ ucfirst($paymentMethod) }} Personal Number using Send Money option.</div>
                </div>
                @else
                <div class="pay-info">
                    <div class="pay-info-label">How to Pay</div>
                    <div class="pay-info-note" style="margin-top: 0;">
                        1. Open {{ ucfirst($paymentMethod) }} app<br>
                        2. Send exactly ৳{{ number_format($amount, 2) }}<br>
                        3. Include reference in note
                    </div>
                </div>
                @endif
            </div>
            
            <!-- Buttons -->
            <div class="btn-row">
                <button id="verify-btn" class="btn btn-primary" type="button">Verify Manually</button>
                <button id="cancel-btn" class="btn btn-secondary" type="button">Cancel</button>
            </div>
            
            <!-- Timer -->
            <div class="timer">Time remaining: <strong id="timer-value">5:00</strong></div>
        </div>
        
        <p class="footer">Powered by <a href="https://vendweave.com/" target="_blank" rel="noopener">VendWeave</a></p>
    </div>
    
    <script>
        (function() {
            'use strict';
            
            const config = {
                orderId: @json($orderId),
                amount: @json($amount),
                paymentMethod: @json($paymentMethod),
                reference: @json($reference ?? null),
                pollUrl: @json($pollUrl),
                cancelUrl: @json($cancelUrl),
                pollingInterval: {{ $pollingInterval }},
                maxAttempts: {{ $maxAttempts }},
                timeoutSeconds: 300 // Fixed 5 minutes
            };
            
            let pollCount = 0;
            let timeRemaining = config.timeoutSeconds; // Start at 300 seconds (5:00)
            let pollTimer = null;
            let countdownTimer = null;
            let isPolling = false;
            let pageLoadTime = Date.now();
            
            const statusBar = document.getElementById('status-bar');
            const statusText = document.getElementById('status-text');
            const errorBox = document.getElementById('error-box');
            const trxInput = document.getElementById('trx-id');
            const verifyBtn = document.getElementById('verify-btn');
            const cancelBtn = document.getElementById('cancel-btn');
            const timerValue = document.getElementById('timer-value');
            
            // Initialize timer display immediately
            updateTimerDisplay();
            
            function startPolling() {
                if (isPolling) return;
                isPolling = true;
                pollTimer = setInterval(poll, config.pollingInterval);
                countdownTimer = setInterval(updateCountdown, 1000);
                poll();
            }
            
            function stopPolling() {
                isPolling = false;
                clearInterval(pollTimer);
                clearInterval(countdownTimer);
            }
            
            async function poll() {
                pollCount++;
                if (timeRemaining <= 0) { handleTimeout(); return; }
                
                try {
                    const params = new URLSearchParams({
                        amount: config.amount,
                        payment_method: config.paymentMethod
                    });
                    
                    if (config.reference) params.append('reference', config.reference);
                    const trxId = trxInput.value.trim();
                    if (trxId) params.append('trx_id', trxId);
                    
                    const response = await fetch(`${config.pollUrl}?${params}`, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    
                    const data = await response.json();
                    handleResponse(data);
                } catch (error) {
                    console.error('Poll error:', error);
                    showError('Connection error. Retrying...');
                }
            }
            
            function handleResponse(data) {
                hideError();
                switch (data.status) {
                    case 'confirmed':
                    case 'used':
                    case 'success':
                        handleSuccess(data);
                        break;
                    case 'verified':
                        updateStatus('pending', 'Confirming transaction...');
                        break;
                    case 'pending':
                        updateStatus('pending', 'Waiting for payment...');
                        break;
                    case 'failed':
                        handleError(data.error_code || 'FAILED', data.error_message || 'Payment failed');
                        break;
                    case 'expired':
                        handleError('EXPIRED', 'Transaction has expired');
                        break;
                }
            }
            
            function handleSuccess(data) {
                stopPolling();
                updateStatus('confirmed', 'Payment Verified!');
                verifyBtn.disabled = true;
                trxInput.disabled = true;
                setTimeout(() => { if (data.redirect_url) window.location.href = data.redirect_url; }, 1200);
            }
            
            function handleError(code, message) {
                stopPolling();
                updateStatus('failed', 'Verification Failed');
                showError(message);
                verifyBtn.textContent = 'Retry';
                verifyBtn.disabled = false;
            }
            
            function handleTimeout() {
                stopPolling();
                updateStatus('failed', 'Payment Timed Out');
                
                // Build fail URL with reason
                const failReason = encodeURIComponent('Payment verification timed out after 5 minutes. No payment was received within the allowed time.');
                const failUrl = config.cancelUrl + (config.cancelUrl.includes('?') ? '&' : '?') + 
                    'status=failed&reason=timeout&message=' + failReason;
                
                showError('Payment verification timed out. Redirecting...');
                
                setTimeout(() => {
                    window.location.href = failUrl;
                }, 1500);
            }
            
            function updateStatus(status, text) {
                statusBar.className = 'status-bar status-' + status;
                statusText.textContent = text;
                
                if (status === 'pending') {
                    statusBar.innerHTML = '<div class="spinner"></div><span>' + text + '</span>';
                } else if (status === 'confirmed') {
                    statusBar.innerHTML = '<span style="font-size:18px">✓</span><span>' + text + '</span>';
                } else {
                    statusBar.innerHTML = '<span style="font-size:18px">✕</span><span>' + text + '</span>';
                }
            }
            
            function updateCountdown() {
                timeRemaining--;
                updateTimerDisplay();
                
                if (timeRemaining <= 0) {
                    handleTimeout();
                }
            }
            
            function updateTimerDisplay() {
                const m = Math.floor(Math.max(0, timeRemaining) / 60);
                const s = Math.max(0, timeRemaining) % 60;
                timerValue.textContent = `${m}:${s.toString().padStart(2, '0')}`;
                
                // Change color when time is running low
                if (timeRemaining <= 60) {
                    timerValue.style.color = 'var(--danger)';
                } else if (timeRemaining <= 120) {
                    timerValue.style.color = 'var(--warning)';
                }
            }
            
            function showError(msg) { errorBox.textContent = msg; errorBox.style.display = 'block'; }
            function hideError() { errorBox.style.display = 'none'; }
            
            verifyBtn.addEventListener('click', function() {
                if (this.textContent === 'Retry') {
                    // Reset only poll count, not timer (timer continues from page load)
                    pollCount = 0;
                    this.textContent = 'Verify Manually';
                    hideError();
                    updateStatus('pending', 'Verifying payment...');
                    startPolling();
                } else {
                    poll();
                }
            });
            
            cancelBtn.addEventListener('click', function() {
                stopPolling();
                const cancelReason = encodeURIComponent('Payment was cancelled by the user.');
                const cancelUrl = config.cancelUrl + (config.cancelUrl.includes('?') ? '&' : '?') + 
                    'status=cancelled&reason=user_cancelled&message=' + cancelReason;
                window.location.href = cancelUrl;
            });
            
            trxInput.addEventListener('keypress', function(e) { if (e.key === 'Enter') poll(); });
            
            // Handle page visibility - timer keeps running even when minimized
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    // Page is now visible - recalculate time based on page load
                    const elapsedSeconds = Math.floor((Date.now() - pageLoadTime) / 1000);
                    timeRemaining = Math.max(0, config.timeoutSeconds - elapsedSeconds);
                    updateTimerDisplay();
                    
                    if (timeRemaining <= 0) {
                        handleTimeout();
                    }
                }
            });
            
            startPolling();
        })();
        
        function copyReference() {
            const refValue = document.getElementById('ref-value');
            const copyBtn = document.getElementById('copy-ref-btn');
            
            if (refValue && navigator.clipboard) {
                navigator.clipboard.writeText(refValue.innerText).then(function() {
                    copyBtn.classList.add('copied');
                    copyBtn.innerHTML = '✓ Copied!';
                    setTimeout(() => { copyBtn.classList.remove('copied'); copyBtn.innerHTML = '📋 Copy Reference'; }, 2000);
                }).catch(function() { fallbackCopy(refValue.innerText); });
            } else {
                fallbackCopy(refValue ? refValue.innerText : '');
            }
        }
        
        function fallbackCopy(text) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;left:-9999px';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            const copyBtn = document.getElementById('copy-ref-btn');
            if (copyBtn) {
                copyBtn.classList.add('copied');
                copyBtn.innerHTML = '✓ Copied!';
                setTimeout(() => { copyBtn.classList.remove('copied'); copyBtn.innerHTML = '📋 Copy Reference'; }, 2000);
            }
        }
    </script>
</body>
</html>
>>>>>>> 0a5418720283cd84f20570eafde1731e8cc3be23
