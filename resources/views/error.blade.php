<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ isset($isCancelled) && $isCancelled ? 'Payment Cancelled' : 'Payment Failed' }} - Order #{{ $orderId ?? 'Unknown' }}</title>
    <style>
        :root {
            --danger: #ef4444;
            --warning: #f59e0b;
            --danger-bg: rgba(239, 68, 68, 0.1);
            --warning-bg: rgba(245, 158, 11, 0.1);
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
            max-width: 420px;
        }
        
        .card {
            background: var(--bg-secondary);
            border-radius: 16px;
            padding: 40px 32px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        
        .error-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 36px;
        }
        
        .error-icon.danger {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        .error-icon.warning {
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        h1.danger {
            color: var(--danger);
        }
        
        h1.warning {
            color: var(--warning);
        }
        
        .subtitle {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 24px;
            line-height: 1.6;
        }
        
        .error-code {
            background: var(--bg-card);
            border-radius: 8px;
            padding: 12px 16px;
            font-family: monospace;
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 24px;
            word-break: break-all;
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
        }
        
        .btn-primary {
            background: #6366f1;
            color: white;
        }
        
        .btn-primary:hover {
            background: #4f46e5;
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
            @if(isset($isCancelled) && $isCancelled)
            <div class="error-icon warning">⏸</div>
            <h1 class="warning">Payment Cancelled</h1>
            @else
            <div class="error-icon danger">✕</div>
            <h1 class="danger">Payment Failed</h1>
            @endif
            
            <p class="subtitle">{{ $errorMessage ?? 'An error occurred during payment verification.' }}</p>
            
            @if(isset($errorCode) && $errorCode !== 'CANCELLED')
            <div class="error-code">
                Error Code: {{ $errorCode }}
            </div>
            @endif
            
            @if(isset($retryUrl) && $retryUrl)
            <a href="{{ $retryUrl }}" class="btn btn-primary">Try Again</a>
            @endif
            
            <a href="/" class="btn btn-secondary">Return to Shop</a>
        </div>
        
        <p class="footer-text">
            Powered by VendWeave Gateway
        </p>
    </div>
</body>
</html>
