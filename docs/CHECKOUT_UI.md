# VendWeave Checkout UI Components

Ready-to-use চেকআউট পেজ ডিজাইন - কপি করে সরাসরি ব্যবহার করুন!

---

## 🎨 Modern Payment Gateway Selector

### Preview

```
┌─────────────────────────────────────────────────────────────────┐
│                    💳 Select Payment Method                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│   ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐       │
│   │  bKash   │  │  Nagad   │  │  Rocket  │  │   Upay   │       │
│   │   💗     │  │   🧡     │  │   💜     │  │   💚     │       │
│   └──────────┘  └──────────┘  └──────────┘  └──────────┘       │
│                                                                  │
│   ┌─────────────────────────────────────────────────────────┐  │
│   │                    [ Pay ৳1,250.00 ]                     │  │
│   └─────────────────────────────────────────────────────────┘  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📦 Complete HTML Component

নিচের কোড কপি করে আপনার checkout blade file এ পেস্ট করুন:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Select Payment</title>
    <style>
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --bkash: #e2136e;
            --nagad: #f26522;
            --rocket: #8b2c8b;
            --upay: #00a651;
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --bg-hover: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --border: #475569;
            --success: #10b981;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .checkout-container {
            width: 100%;
            max-width: 520px;
        }

        .checkout-card {
            background: var(--bg-card);
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid var(--border);
        }

        .checkout-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .checkout-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .checkout-header p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Order Summary */
        .order-summary {
            background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 28px;
            text-align: center;
        }

        .order-summary .label {
            font-size: 13px;
            color: rgba(255,255,255,0.8);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .order-summary .amount {
            font-size: 42px;
            font-weight: 800;
            color: white;
        }

        .order-summary .amount .currency {
            font-size: 20px;
            font-weight: 500;
            opacity: 0.9;
        }

        /* Payment Methods */
        .payment-section-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .payment-section-title::before,
        .payment-section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 28px;
        }

        .payment-option {
            position: relative;
        }

        .payment-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .payment-option label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px 16px;
            background: var(--bg-dark);
            border: 2px solid var(--border);
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .payment-option label:hover {
            border-color: var(--text-secondary);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }

        .payment-option input:checked + label {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.1);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.2);
        }

        .payment-option input:checked + label::after {
            content: '✓';
            position: absolute;
            top: 8px;
            right: 8px;
            width: 22px;
            height: 22px;
            background: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: white;
            font-weight: bold;
        }

        .payment-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            font-size: 24px;
            font-weight: 800;
            color: white;
        }

        .payment-option.bkash .payment-icon { background: var(--bkash); }
        .payment-option.nagad .payment-icon { background: var(--nagad); }
        .payment-option.rocket .payment-icon { background: var(--rocket); }
        .payment-option.upay .payment-icon { background: var(--upay); }

        .payment-option input:checked + label .payment-icon {
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .payment-name {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .payment-hint {
            font-size: 11px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        /* Pay Button */
        .pay-button {
            width: 100%;
            padding: 18px 32px;
            font-size: 17px;
            font-weight: 700;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
        }

        .pay-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(99, 102, 241, 0.4);
        }

        .pay-button:active {
            transform: translateY(0);
        }

        .pay-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .pay-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .pay-button:hover::before {
            left: 100%;
        }

        /* Security Badge */
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            padding: 12px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 10px;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .security-badge span {
            font-size: 13px;
            color: var(--success);
            font-weight: 500;
        }

        /* Footer */
        .checkout-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        /* Responsive */
        @media (max-width: 480px) {
            .checkout-card {
                padding: 24px 20px;
            }
            
            .order-summary .amount {
                font-size: 36px;
            }
            
            .payment-methods {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            
            .payment-option label {
                padding: 16px 12px;
            }
            
            .payment-icon {
                width: 48px;
                height: 48px;
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <form action="{{ route('checkout.process') }}" method="POST" class="checkout-card">
            @csrf
            
            <div class="checkout-header">
                <h1>Complete Your Order</h1>
                <p>Order #{{ $order->id ?? '12345' }} • Select payment method below</p>
            </div>
            
            <div class="order-summary">
                <div class="label">Total Amount</div>
                <div class="amount">
                    <span class="currency">৳</span>{{ number_format($total ?? 1250, 2) }}
                </div>
            </div>
            
            <div class="payment-section-title">
                Select Payment Method
            </div>
            
            <div class="payment-methods">
                <div class="payment-option bkash">
                    <input type="radio" name="payment_method" value="bkash" id="bkash" required>
                    <label for="bkash">
                        <div class="payment-icon">b</div>
                        <span class="payment-name">bKash</span>
                        <span class="payment-hint">Mobile Banking</span>
                    </label>
                </div>
                
                <div class="payment-option nagad">
                    <input type="radio" name="payment_method" value="nagad" id="nagad">
                    <label for="nagad">
                        <div class="payment-icon">N</div>
                        <span class="payment-name">Nagad</span>
                        <span class="payment-hint">Digital Payment</span>
                    </label>
                </div>
                
                <div class="payment-option rocket">
                    <input type="radio" name="payment_method" value="rocket" id="rocket">
                    <label for="rocket">
                        <div class="payment-icon">R</div>
                        <span class="payment-name">Rocket</span>
                        <span class="payment-hint">DBBL Mobile</span>
                    </label>
                </div>
                
                <div class="payment-option upay">
                    <input type="radio" name="payment_method" value="upay" id="upay">
                    <label for="upay">
                        <div class="payment-icon">U</div>
                        <span class="payment-name">Upay</span>
                        <span class="payment-hint">UCB Fintech</span>
                    </label>
                </div>
            </div>
            
            <button type="submit" class="pay-button" id="payBtn">
                <span>💳</span>
                <span>Pay ৳{{ number_format($total ?? 1250, 2) }}</span>
            </button>
            
            <div class="security-badge">
                <span>🔒</span>
                <span>Secured by VendWeave • 256-bit SSL Encryption</span>
            </div>
            
            <div class="checkout-footer">
                By completing this payment, you agree to our Terms of Service
            </div>
        </form>
    </div>
    
    <script>
        // Enable/disable pay button based on selection
        const payBtn = document.getElementById('payBtn');
        const radios = document.querySelectorAll('input[name="payment_method"]');
        
        radios.forEach(radio => {
            radio.addEventListener('change', function() {
                payBtn.disabled = false;
                
                // Update button text with selected method
                const methodName = this.nextElementSibling.querySelector('.payment-name').textContent;
                payBtn.innerHTML = `<span>💳</span><span>Pay with ${methodName}</span>`;
            });
        });
        
        // Initially disable if nothing selected
        payBtn.disabled = !document.querySelector('input[name="payment_method"]:checked');
    </script>
</body>
</html>
```

---

## 🎯 Blade Template Version

Laravel Blade এ ব্যবহার করতে:

### `resources/views/checkout.blade.php`

```blade
@extends('layouts.app')

@section('title', 'Checkout')

@push('styles')
<style>
    /* উপরের CSS কোড এখানে পেস্ট করুন */
</style>
@endpush

@section('content')
<div class="checkout-container">
    <form action="{{ route('checkout.process') }}" method="POST" class="checkout-card">
        @csrf
        
        <div class="checkout-header">
            <h1>Complete Your Order</h1>
            <p>Order #{{ $order->id }} • Select payment method</p>
        </div>
        
        <div class="order-summary">
            <div class="label">Total Amount</div>
            <div class="amount">
                <span class="currency">৳</span>{{ number_format($order->total, 2) }}
            </div>
        </div>
        
        <div class="payment-section-title">
            Select Payment Method
        </div>
        
        <div class="payment-methods">
            @foreach(['bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket', 'upay' => 'Upay'] as $value => $name)
            <div class="payment-option {{ $value }}">
                <input type="radio" name="payment_method" value="{{ $value }}" id="{{ $value }}" @if($loop->first) required @endif>
                <label for="{{ $value }}">
                    <div class="payment-icon">{{ strtoupper(substr($name, 0, 1)) }}</div>
                    <span class="payment-name">{{ $name }}</span>
                </label>
            </div>
            @endforeach
        </div>
        
        <button type="submit" class="pay-button">
            💳 Pay ৳{{ number_format($order->total, 2) }}
        </button>
        
        <div class="security-badge">
            🔒 Secured by VendWeave
        </div>
    </form>
</div>
@endsection
```

---

## 🌈 Light Theme Variant

Light theme চাইলে CSS এ এই variables পরিবর্তন করুন:

```css
:root {
    --bg-dark: #f8fafc;
    --bg-card: #ffffff;
    --bg-hover: #f1f5f9;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --border: #e2e8f0;
}
```

---

## 📱 Mobile Optimized

এই ডিজাইন fully responsive:
- ✅ Desktop (1024px+)
- ✅ Tablet (768px - 1023px)
- ✅ Mobile (< 768px)
- ✅ Small Mobile (< 380px)

---

## 🚀 Integration Tips

1. **CSS আলাদা ফাইলে রাখুন**: `public/css/checkout.css`
2. **Amount Dynamic করুন**: `{{ $order->total }}` ব্যবহার করুন
3. **Logo যোগ করুন**: `.payment-icon` এ img tag ব্যবহার করুন
4. **Animation কমান চাইলে**: `transition` values কমান

---

## 🎨 Color Customization

Brand color পরিবর্তন করতে:

```css
:root {
    --primary: #your-brand-color;
    --primary-hover: #your-hover-color;
}
```

---

**Happy Coding! 🎉**
