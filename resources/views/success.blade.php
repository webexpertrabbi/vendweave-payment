<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Order #{{ $orderId }}</title>
    <style>
        :root {
            --success: #10b981;
            --success-bg: rgba(16, 185, 129, 0.1);
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
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
            max-width: 420px;
        }
        
        .card {
            background: var(--bg-secondary);
            border-radius: 16px;
            padding: 40px 32px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--success-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            animation: scaleIn 0.5s ease-out;
        }
        
        @keyframes scaleIn {
            0% { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .success-icon svg {
            width: 40px;
            height: 40px;
            color: var(--success);
        }
        
        .checkmark {
            stroke-dasharray: 60;
            stroke-dashoffset: 60;
            animation: checkmark 0.5s ease-out 0.3s forwards;
        }
        
        @keyframes checkmark {
            to { stroke-dashoffset: 0; }
        }
        
        h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--success);
        }
        
        .subtitle {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 32px;
        }
        
        .details {
            background: rgba(16, 185, 129, 0.05);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }
        
        .detail-row:not(:last-child) {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .detail-label {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .detail-value {
            font-weight: 600;
            font-size: 14px;
        }
        
        .btn {
            width: 100%;
            padding: 14px 24px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
            background: var(--success);
            color: white;
        }
        
        .btn:hover {
            filter: brightness(1.1);
        }
        
        .footer-text {
            text-align: center;
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 24px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="success-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <path class="checkmark" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            
            <h1>Payment Successful!</h1>
            <p class="subtitle">Your payment has been verified and confirmed.</p>
            
            <div class="details">
                <div class="detail-row">
                    <span class="detail-label">Order ID</span>
                    <span class="detail-value">#{{ $orderId }}</span>
                </div>
                @if($amount)
                <div class="detail-row">
                    <span class="detail-label">Amount</span>
                    <span class="detail-value">৳{{ number_format($amount, 2) }}</span>
                </div>
                @endif
                @if($trxId)
                <div class="detail-row">
                    <span class="detail-label">Transaction ID</span>
                    <span class="detail-value">{{ $trxId }}</span>
                </div>
                @endif
                @if($paymentMethod)
                <div class="detail-row">
                    <span class="detail-label">Payment Method</span>
                    <span class="detail-value">{{ ucfirst($paymentMethod) }}</span>
                </div>
                @endif
            </div>
            
            <a href="/" class="btn">Continue Shopping</a>
        </div>
        
        <p class="footer-text">
            Powered by VendWeave Gateway
        </p>
    </div>
</body>
</html>
