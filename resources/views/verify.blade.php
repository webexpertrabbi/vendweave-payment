<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Verify Payment - Order #{{ $orderId }}</title>
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-card: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --border: #475569;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 700px;
        }
        
        .card {
            background: var(--bg-secondary);
            border-radius: 16px;
            padding: 32px 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        
        .header {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .payment-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 28px;
            font-weight: 700;
        }
        
        .header h1 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .header p {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .amount-display {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .amount-label {
            font-size: 13px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .amount-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .amount-currency {
            font-size: 18px;
            color: var(--text-secondary);
            margin-left: 4px;
        }
        
        .status-container {
            text-align: center;
            padding: 16px 0;
            border-bottom: 1px solid var(--border);
            margin-bottom: 20px;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            border-radius: 100px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .status-pending {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning);
        }
        
        .status-confirmed {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }
        
        .status-failed {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
        }
        
        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .pulse {
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .trx-input-container {
            margin-bottom: 16px;
        }
        
        .trx-label {
            display: block;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        
        .trx-input {
            width: 100%;
            padding: 14px 16px;
            font-size: 16px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text-primary);
            outline: none;
            transition: border-color 0.2s;
        }
        
        .trx-input:focus {
            border-color: var(--primary);
        }
        
        .trx-input::placeholder {
            color: var(--text-secondary);
        }
        
        .instructions {
            background: var(--bg-card);
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 20px;
        }
        
        .instructions h3 {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        
        .instructions ol {
            padding-left: 18px;
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.7;
        }
        
        .btn {
            width: 100%;
            padding: 14px 24px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-secondary {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border);
            margin-top: 12px;
        }
        
        .btn-secondary:hover {
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .timer {
            text-align: center;
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 16px;
        }
        
        .timer-value {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 16px;
            font-size: 14px;
            color: var(--danger);
            display: none;
        }
        
        .footer-text {
            text-align: center;
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 24px;
        }
        
        /* Two Column Layout for Desktop */
        @media (min-width: 600px) {
            .two-column {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
            
            .btn-group {
                display: flex;
                gap: 12px;
            }
            
            .btn-group .btn {
                flex: 1;
                margin-top: 0;
            }
        }
        
        /* Responsive for Mobile */
        @media (max-width: 599px) {
            .card {
                padding: 20px;
            }
            
            .amount-value {
                font-size: 28px;
            }
            
            .two-column {
                display: block;
            }
        }
        
        /* Extra small screens */
        @media (max-width: 380px) {
            .card {
                padding: 16px;
            }
            
            .amount-value {
                font-size: 24px;
            }
        }
        
        /* Reference Box Styles */
        .reference-box {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            text-align: center;
            border: 2px solid #d97706;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }
        
        .reference-label {
            font-size: 12px;
            font-weight: 600;
            color: #78350f;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .reference-value {
            font-size: 28px;
            font-weight: 800;
            color: #1e293b;
            font-family: 'Courier New', monospace;
            letter-spacing: 3px;
            margin-bottom: 10px;
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
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .reference-copy-btn:hover {
            background: #0f172a;
            transform: scale(1.02);
        }
        
        .reference-copy-btn.copied {
            background: #059669;
            color: white;
        }
        
        .reference-note {
            font-size: 11px;
            color: #92400e;
            margin-top: 10px;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                @if($paymentMethodInfo)
                <div class="payment-icon" style="background: {{ $paymentMethodInfo['color'] ?? 'var(--primary)' }}22;">
                    <span style="color: {{ $paymentMethodInfo['color'] ?? 'var(--primary)' }}">
                        {{ strtoupper(substr($paymentMethod, 0, 1)) }}
                    </span>
                </div>
                <h1>Pay with {{ $paymentMethodInfo['name'] ?? ucfirst($paymentMethod) }}</h1>
                @else
                <h1>Verify Payment</h1>
                @endif
                <p>Order #{{ $orderId }}</p>
            </div>
            
            <div class="amount-display">
                <div class="amount-label">Amount to Pay</div>
                <div class="amount-value">
                    ৳{{ number_format($amount, 2) }}<span class="amount-currency">BDT</span>
                </div>
            </div>
            
            @if($reference ?? null)
            <div class="reference-box">
                <div class="reference-label">⚠️ Include This Reference In Your Payment</div>
                <div class="reference-value" id="ref-value">{{ $reference }}</div>
                <button class="reference-copy-btn" id="copy-ref-btn" onclick="copyReference()">
                    📋 Copy Reference
                </button>
                <div class="reference-note">Send money with this reference in the note/message field</div>
            </div>
            @endif
            
            <div class="status-container">
                <div id="status-indicator" class="status-indicator status-pending">
                    <div class="spinner"></div>
                    <span id="status-text">Waiting for payment...</span>
                </div>
            </div>
            
            <div id="error-message" class="error-message"></div>
            
            <div class="two-column">
                <div class="trx-input-container">
                    <label class="trx-label" for="trx-id">Transaction ID (Optional)</label>
                    <input 
                        type="text" 
                        id="trx-id" 
                        class="trx-input" 
                        placeholder="Enter your {{ ucfirst($paymentMethod) }} TRX ID"
                        autocomplete="off"
                    >
                </div>
                
                <div class="instructions">
                    @if(isset($paymentMethodInfo['number']))
                        <div style="background: rgba(0,0,0,0.2); padding: 12px; border-radius: 8px; margin-bottom: 12px; border: 1px dashed var(--border);">
                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Send Money To ({{ ucfirst($paymentMethodInfo['type'] ?? 'Personal') }})</div>
                            <div style="font-size: 18px; font-weight: 700; color: var(--primary); letter-spacing: 1px; font-family: monospace;">{{ $paymentMethodInfo['number'] }}</div>
                        </div>
                        <div style="font-size: 13px; color: var(--text-secondary); line-height: 1.6; margin-bottom: 12px;">
                            {{ $paymentMethodInfo['instruction'] ?? 'Send exact amount to the number above.' }}
                        </div>
                    @else
                        <h3>How to Pay</h3>
                        <ol>
                            <li>Open your {{ ucfirst($paymentMethod) }} app</li>
                            <li>Send exactly ৳{{ number_format($amount, 2) }}</li>
                            <li>Wait for auto-verify or enter TRX ID</li>
                        </ol>
                    @endif
                </div>
            </div>
            
            <div class="btn-group">
                <button id="verify-btn" class="btn btn-primary" type="button">
                    Verify Manually
                </button>
                
                <button id="cancel-btn" class="btn btn-secondary" type="button">
                    Cancel
                </button>
            </div>
            
            <div class="timer">
                Time remaining: <span id="timer-value" class="timer-value">5:00</span>
            </div>
        </div>
        
        <p class="footer-text">
            Powered by VendWeave Gateway
        </p>
    </div>
    
    <script>
        (function() {
            'use strict';
            
            // Configuration from server
            const config = {
                orderId: @json($orderId),
                amount: @json($amount),
                paymentMethod: @json($paymentMethod),
                reference: @json($reference ?? null),
                pollUrl: @json($pollUrl),
                cancelUrl: @json($cancelUrl),
                pollingInterval: {{ $pollingInterval }},
                maxAttempts: {{ $maxAttempts }},
                timeout: {{ $timeout }}, // Remaining time
                totalDuration: {{ config('vendweave.polling.timeout_seconds', 300) }} // Full duration for retry
            };
            
            // State
            let pollCount = 0;
            let timeRemaining = config.timeout;
            let pollTimer = null;
            let countdownTimer = null;
            let isPolling = false;
            
            // DOM elements
            const statusIndicator = document.getElementById('status-indicator');
            const statusText = document.getElementById('status-text');
            const errorMessage = document.getElementById('error-message');
            const trxInput = document.getElementById('trx-id');
            const verifyBtn = document.getElementById('verify-btn');
            const cancelBtn = document.getElementById('cancel-btn');
            const timerValue = document.getElementById('timer-value');
            
            // Start polling
            function startPolling() {
                if (isPolling) return;
                isPolling = true;
                
                pollTimer = setInterval(poll, config.pollingInterval);
                countdownTimer = setInterval(updateCountdown, 1000);
                
                // Initial poll
                poll();
            }
            
            // Stop polling
            function stopPolling() {
                isPolling = false;
                clearInterval(pollTimer);
                clearInterval(countdownTimer);
            }
            
            // Poll for status
            async function poll() {
                pollCount++;
                
                if (pollCount > config.maxAttempts) {
                    handleTimeout();
                    return;
                }
                
                try {
                    const params = new URLSearchParams({
                        amount: config.amount,
                        payment_method: config.paymentMethod
                    });
                    
                    // Add reference if available
                    if (config.reference) {
                        params.append('reference', config.reference);
                    }
                    
                    const trxId = trxInput.value.trim();
                    if (trxId) {
                        params.append('trx_id', trxId);
                    }
                    
                    const response = await fetch(`${config.pollUrl}?${params}`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    const data = await response.json();
                    handleResponse(data);
                    
                } catch (error) {
                    console.error('Poll error:', error);
                    showError('Connection error. Retrying...');
                }
            }
            
            // Handle API response
            // REDIRECT RULES (v1.10.0):
            // - pending: stay on verify page
            // - verified: keep polling (transaction seen, awaiting confirm)
            // - confirmed/used: redirect to success ✅
            // - failed/expired: redirect to failed ✅
            function handleResponse(data) {
                hideError();
                
                switch (data.status) {
                    case 'confirmed':
                    case 'used':
                        // Payment verified and consumed - redirect to success ✅
                        handleSuccess(data);
                        break;
                    case 'success':
                        // Legacy status - treat as confirmed
                        handleSuccess(data);
                        break;
                    case 'verified':
                        // Transaction found, awaiting confirmation
                        updateStatus('pending', 'Transaction verified, confirming...');
                        break;
                    case 'pending':
                        updateStatus('pending', 'Waiting for payment...');
                        break;
                    case 'failed':
                        // Redirect to failed ✅
                        handleError(data.error_code || 'FAILED', data.error_message || 'Payment failed');
                        break;
                    case 'expired':
                        handleError('EXPIRED', 'This transaction has expired');
                        break;
                }
            }
            
            // Handle successful verification
            function handleSuccess(data) {
                stopPolling();
                updateStatus('confirmed', 'Payment Verified!');
                
                verifyBtn.disabled = true;
                trxInput.disabled = true;
                
                // Redirect after short delay
                setTimeout(() => {
                    if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                    }
                }, 1500);
            }
            
            // Handle error
            function handleError(code, message) {
                stopPolling();
                updateStatus('failed', 'Verification Failed');
                showError(message);
                
                verifyBtn.textContent = 'Retry';
                verifyBtn.disabled = false;
            }
            
            // Handle timeout
            function handleTimeout() {
                stopPolling();
                updateStatus('failed', 'Verification Timed Out');
                showError('Payment verification timed out. Please try again.');
                
                verifyBtn.textContent = 'Retry';
                verifyBtn.disabled = false;
            }
            
            // Update status display
            function updateStatus(status, text) {
                statusIndicator.className = 'status-indicator status-' + status;
                statusText.textContent = text;
                
                if (status === 'pending') {
                    statusIndicator.innerHTML = '<div class="spinner"></div><span>' + text + '</span>';
                } else if (status === 'confirmed') {
                    statusIndicator.innerHTML = '<span>✓</span><span>' + text + '</span>';
                } else {
                    statusIndicator.innerHTML = '<span>✕</span><span>' + text + '</span>';
                }
            }
            
            // Update countdown timer
            function updateCountdown() {
                timeRemaining--;
                
                if (timeRemaining <= 0) {
                    handleTimeout();
                    return;
                }
                
                const minutes = Math.floor(timeRemaining / 60);
                const seconds = timeRemaining % 60;
                timerValue.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            }
            
            // Show error
            function showError(message) {
                errorMessage.textContent = message;
                errorMessage.style.display = 'block';
            }
            
            // Hide error
            function hideError() {
                errorMessage.style.display = 'none';
            }
            
            // Manual verify button
            verifyBtn.addEventListener('click', function() {
                if (this.textContent === 'Retry') {
                    pollCount = 0;
                    timeRemaining = config.timeout;
                    this.textContent = 'Verify Manually';
                    startPolling();
                } else {
                    poll();
                }
            });
            
            // Cancel button
            cancelBtn.addEventListener('click', function() {
                stopPolling();
                window.location.href = config.cancelUrl;
            });
            
            // TRX input - trigger immediate poll on enter
            trxInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    poll();
                }
            });
            
            // Start on load
            startPolling();
        })();
        
        // Copy reference to clipboard
        function copyReference() {
            const refValue = document.getElementById('ref-value');
            const copyBtn = document.getElementById('copy-ref-btn');
            
            if (refValue && navigator.clipboard) {
                navigator.clipboard.writeText(refValue.innerText).then(function() {
                    // Visual feedback
                    copyBtn.classList.add('copied');
                    copyBtn.innerHTML = '✓ Copied!';
                    
                    setTimeout(function() {
                        copyBtn.classList.remove('copied');
                        copyBtn.innerHTML = '📋 Copy Reference';
                    }, 2000);
                }).catch(function() {
                    // Fallback for older browsers
                    fallbackCopy(refValue.innerText);
                });
            } else {
                fallbackCopy(refValue ? refValue.innerText : '');
            }
        }
        
        function fallbackCopy(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            
            const copyBtn = document.getElementById('copy-ref-btn');
            if (copyBtn) {
                copyBtn.classList.add('copied');
                copyBtn.innerHTML = '✓ Copied!';
                setTimeout(function() {
                    copyBtn.classList.remove('copied');
                    copyBtn.innerHTML = '📋 Copy Reference';
                }, 2000);
            }
        }
    </script>
</body>
</html>
