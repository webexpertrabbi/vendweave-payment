# VendWeave Laravel Integration Guide

সম্পূর্ণ Step-by-Step গাইড - Beginner Friendly! 🚀

---

## 📋 শুরু করার আগে যা লাগবে

| প্রয়োজনীয়তা | বিবরণ |
|---------------|-------|
| PHP | 8.1 বা তার উপরে |
| Laravel | 10.x / 11.x / 12.x |
| Composer | Installed |
| VendWeave Account | API Key, Secret, Store Slug (Dashboard থেকে নিন) |

---

## 🎯 আপনাকে কোন কোন ফাইলে কাজ করতে হবে

```
📁 your-laravel-project/
├── 📄 .env                          ← API credentials যোগ করুন
├── 📄 composer.json                 ← Package install হবে
├── 📁 config/
│   └── 📄 vendweave.php             ← Auto-generated (publish করলে)
├── 📁 app/
│   ├── 📁 Http/Controllers/
│   │   └── 📄 CheckoutController.php ← আপনি তৈরি করবেন
│   ├── 📁 Listeners/
│   │   ├── 📄 MarkOrderAsPaid.php    ← আপনি তৈরি করবেন
│   │   └── 📄 HandleFailedPayment.php ← আপনি তৈরি করবেন
│   └── 📁 Providers/
│       └── 📄 AppServiceProvider.php  ← Events register করুন (Laravel 11+)
├── 📁 routes/
│   └── 📄 web.php                    ← Routes যোগ করুন
└── 📁 resources/views/
    └── 📄 checkout.blade.php         ← আপনার checkout page
```

---

# 🚀 STEP 1: Package Install

Terminal এ নিচের commands চালান:

```bash
# Package install
composer require vendweave/payment

# Config file publish
php artisan vendor:publish --tag=vendweave-config

# Assets publish (payment gateway logos)
php artisan vendor:publish --tag=vendweave-assets
```

✅ এতে:
- `config/vendweave.php` ফাইল তৈরি হবে
- `public/vendor/vendweave/images/` ফোল্ডারে payment gateway logos কপি হবে

---

# 🔑 STEP 2: .env ফাইলে Credentials যোগ করুন

`.env` ফাইল খুলুন এবং নিচের lines যোগ করুন:

```env
#--------------------------------------------
# VendWeave Payment Gateway Configuration
#--------------------------------------------

# API Credentials (VendWeave Dashboard থেকে নিন)
VENDWEAVE_API_KEY=your_api_key_here
VENDWEAVE_API_SECRET=your_api_secret_here
VENDWEAVE_STORE_SLUG=your_store_slug_here
VENDWEAVE_API_ENDPOINT=https://vendweave.com/api

# Payment Numbers (Verification page এ customers দেখবে)
VENDWEAVE_BKASH_NUMBER="01XXXXXXXXX"
VENDWEAVE_NAGAD_NUMBER="01XXXXXXXXX"
VENDWEAVE_ROCKET_NUMBER="01XXXXXXXXX"
VENDWEAVE_UPAY_NUMBER="01XXXXXXXXX"

# Optional Settings
VENDWEAVE_VERIFY_SSL=true
VENDWEAVE_LOGGING=true
```

### 📍 Credentials কোথায় পাবেন?

1. [VendWeave Dashboard](https://vendweave.com) এ Login করুন
2. **Settings → API Keys** এ যান
3. API Key, Secret এবং Store Slug কপি করুন

---

# 🗃️ STEP 3: Database Migration (Optional)

আপনার `orders` টেবিলে নিচের fields থাকলে ভালো হয়:

```bash
php artisan make:migration add_payment_fields_to_orders_table
```

Migration ফাইল এডিট করুন:

```php
<?php
// database/migrations/xxxx_add_payment_fields_to_orders_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // যদি এই columns না থাকে তাহলে যোগ করুন
            $table->string('payment_method')->nullable()->after('total');
            $table->string('trx_id')->nullable()->after('payment_method');
            $table->string('reference')->nullable()->after('trx_id');
            $table->timestamp('paid_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'trx_id', 'reference', 'paid_at']);
        });
    }
};
```

```bash
php artisan migrate
```

---

# 🎨 STEP 4: Checkout Page তৈরি করুন

আপনার checkout page এ payment method selection যোগ করুন। নিচে দুইটা option দেওয়া হলো:

---

## Option A: সুন্দর Payment Gateway UI (Recommended) ⭐

আপনার checkout form এ "Buy Now" বা "Place Order" button এর **ঠিক উপরে** নিচের কোড পেস্ট করুন:

```html
<!-- VendWeave Payment Method Selector -->
<div class="mb-3">
    <label class="form-label mb-2">Payment Method</label>
    <style>
        .pm-card {
            background: #fff;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            box-shadow: 0 1px 4px 0 rgba(0,0,0,0.04);
            transition: all 0.2s ease;
            padding: 8px 12px;
            cursor: pointer;
            position: relative;
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 8px;
        }
        .pm-card:hover {
            border-color: #d1d5db;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px 0 rgba(0,0,0,0.08);
        }
        .pm-card.selected {
            border-color: var(--pm-color, #6366f1);
            box-shadow: 0 0 0 3px var(--pm-color, #6366f1)22;
            transform: translateY(-2px);
        }
        .pm-logo {
            width: 28px;
            height: 28px;
            object-fit: contain;
            flex-shrink: 0;
        }
        .pm-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--pm-color, #6366f1);
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
    </style>
    <div class="d-flex flex-row gap-2 justify-content-start">
        @php
            $pmList = [
                'bkash' => ['label' => 'bKash', 'color' => '#D8005A', 'logo' => asset('vendor/vendweave/images/vendweave-bkash.png')],
                'nagad' => ['label' => 'Nagad', 'color' => '#F9A825', 'logo' => asset('vendor/vendweave/images/vendweave-nagad.png')],
                'rocket' => ['label' => 'Rocket', 'color' => '#7C3AED', 'logo' => asset('vendor/vendweave/images/vendweave-rocket.png')],
                'upay' => ['label' => 'Upay', 'color' => '#00BFAE', 'logo' => asset('vendor/vendweave/images/vendweave-upay.png')],
            ];
        @endphp
        @foreach($pmList as $key => $info)
            <input type="radio" name="payment_method" value="{{ $key }}" id="pm_{{ $key }}" class="d-none" {{ old('payment_method') == $key ? 'checked' : '' }} required>
            <label for="pm_{{ $key }}" class="pm-card" style="--pm-color: {{ $info['color'] }};">
                <img src="{{ $info['logo'] }}" alt="{{ $info['label'] }} Logo" class="pm-logo" loading="lazy">
                <span class="pm-label">{{ $info['label'] }}</span>
            </label>
        @endforeach
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const radios = document.querySelectorAll('input[name="payment_method"]');
            const cards = document.querySelectorAll('.pm-card');
            function updateSelection() {
                cards.forEach((card, idx) => {
                    card.classList.remove('selected');
                    if(radios[idx].checked) card.classList.add('selected');
                });
            }
            radios.forEach(radio => {
                radio.addEventListener('change', updateSelection);
            });
            updateSelection();
        });
    </script>
</div>
<!-- End VendWeave Payment Method Selector -->
```

> ⚠️ **গুরুত্বপূর্ণ:** উপরের কোড কাজ করার জন্য আগে assets publish করতে হবে:
> ```bash
> php artisan vendor:publish --tag=vendweave-assets
> ```
> এতে `public/vendor/vendweave/images/` ফোল্ডারে payment gateway logos কপি হবে।

---

## Option B: সিম্পল Payment Gateway UI

যদি Bootstrap বা fancy UI না চান, তাহলে এই simple version ব্যবহার করুন:

### ফাইল: `resources/views/checkout.blade.php`

```html
<!DOCTYPE html>
<html>
<head>
    <title>Checkout</title>
</head>
<body>
    <h1>Checkout</h1>
    
    <form action="{{ route('checkout.process') }}" method="POST">
        @csrf
        
        <!-- Order Summary -->
        <div>
            <h3>Order Total: ৳{{ number_format($total, 2) }}</h3>
        </div>
        
        <!-- Payment Method Selection -->
        <h4>Select Payment Method:</h4>
        
        <div>
            <label>
                <input type="radio" name="payment_method" value="bkash" required>
                bKash
            </label>
        </div>
        
        <div>
            <label>
                <input type="radio" name="payment_method" value="nagad">
                Nagad
            </label>
        </div>
        
        <div>
            <label>
                <input type="radio" name="payment_method" value="rocket">
                Rocket
            </label>
        </div>
        
        <div>
            <label>
                <input type="radio" name="payment_method" value="upay">
                Upay
            </label>
        </div>
        
        <button type="submit">Pay Now</button>
    </form>
</body>
</html>
```

---

# 🎮 STEP 5: Controller এ VendWeave Integration

আপনার existing `OrderController.php` বা নতুন `CheckoutController.php` এ **তিনটি জিনিস** যোগ করতে হবে:

---

## 📋 তিনটি পরিবর্তন (Summary)

| Step | কি করতে হবে | কোথায় |
|------|-------------|-------|
| 1️⃣ | `payment_method` validation যোগ করুন | `$request->validate()` এ |
| 2️⃣ | `payment_method` database এ save করুন | `Order::create()` এ |
| 3️⃣ | Session set করে VendWeave redirect করুন | Order create এর পরে |

---

## 1️⃣ Validation এ `payment_method` যোগ করুন

```php
$validated = $request->validate([
    // ...আপনার existing validations...
    'payment_method' => ['required', 'in:bkash,nagad,rocket,upay'], // 🆕 এটা যোগ করুন
]);
```

---

## 2️⃣ Order create করার সময় `payment_method` save করুন

```php
$order = Order::create([
    // ...আপনার existing fields...
    'payment_method' => $validated['payment_method'], // 🆕 এটা যোগ করুন
]);
```

---

## 3️⃣ Session set করে VendWeave verify page এ redirect করুন

Order create এর পরে এই কোড যোগ করুন:

```php
// 🆕 VendWeave Integration - এই তিন লাইন যোগ করুন
\Session::put("vendweave_order_{$order->id}", [
    'amount' => $order->total_price, // আপনার total field name
    'payment_method' => $order->payment_method,
]);

return redirect()->route('vendweave.verify', ['order' => $order->id]);
```

---

## 📄 Complete Controller Example

```bash
php artisan make:controller CheckoutController
```

### ফাইল: `app/Http/Controllers/CheckoutController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class CheckoutController extends Controller
{
    /**
     * Checkout page দেখান
     */
    public function show()
    {
        $total = 1250.00; // আপনার cart total
        return view('checkout', compact('total'));
    }
    
    /**
     * Order create এবং Payment process করুন
     */
    public function process(Request $request): RedirectResponse
    {
        // ✅ Step 1: Validation (payment_method সহ)
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_address' => ['required', 'string', 'max:500'],
            'quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'in:bkash,nagad,rocket,upay'], // 🆕
        ]);

        // আপনার price calculation
        $unitPrice = 500.00; // আপনার product price
        $qty = (int)$validated['quantity'];
        $totalPrice = $unitPrice * $qty;

        // ✅ Step 2: Order create (payment_method সহ)
        $order = Order::create([
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'customer_address' => $validated['customer_address'],
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'status' => 'pending',
            'payment_method' => $validated['payment_method'], // 🆕
        ]);

        // ✅ Step 3: VendWeave Integration
        \Session::put("vendweave_order_{$order->id}", [
            'amount' => $order->total_price,
            'payment_method' => $order->payment_method,
        ]);

        // VendWeave verify page এ redirect
        return redirect()->route('vendweave.verify', ['order' => $order->id]);
    }
}
```

---

## 🔄 Existing OrderController এ Integration

যদি আপনার already `OrderController` আছে, তাহলে শুধু এই changes করুন:

### আগে (Before):

```php
public function store(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'customer_name' => ['required'],
        // ...other validations...
    ]);

    $order = Order::create([
        // ...fields...
    ]);

    return redirect()->route('orders.show', $order);
}
```

### পরে (After):

```php
public function store(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'customer_name' => ['required'],
        // ...other validations...
        'payment_method' => ['required', 'in:bkash,nagad,rocket,upay'], // 🆕 যোগ করুন
    ]);

    $order = Order::create([
        // ...existing fields...
        'payment_method' => $validated['payment_method'], // 🆕 যোগ করুন
    ]);

    // 🆕 VendWeave Integration - নিচের কোড যোগ করুন
    \Session::put("vendweave_order_{$order->id}", [
        'amount' => $order->total_price,
        'payment_method' => $order->payment_method,
    ]);

    return redirect()->route('vendweave.verify', ['order' => $order->id]); // 🆕 পরিবর্তন করুন
}
```

---

# 🛣️ STEP 6: Routes যোগ করুন

### ফাইল: `routes/web.php`

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;

// Checkout Routes
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'process'])->name('checkout.process');

// Order Success/Failed Routes (Payment এর পরে redirect হবে)
Route::get('/order/{order}/success', [OrderController::class, 'success'])->name('order.success');
Route::get('/order/{order}/failed', [OrderController::class, 'failed'])->name('order.failed');
```

---

# ⚙️ STEP 7: Config এ Success/Failed Route সেট করুন

### ফাইল: `config/vendweave.php`

```php
<?php

return [
    // ... অন্যান্য config ...
    
    'callbacks' => [
        // Payment success হলে কোথায় redirect হবে
        'success_route' => 'order.success',
        
        // Payment fail হলে কোথায় redirect হবে
        'failed_route' => 'order.failed',
    ],
];
```

---

# 📣 STEP 8: Event Listeners তৈরি করুন

Payment success/fail হলে order update করার জন্য:

```bash
php artisan make:listener MarkOrderAsPaid
php artisan make:listener HandleFailedPayment
```

### ফাইল: `app/Listeners/MarkOrderAsPaid.php`

```php
<?php

namespace App\Listeners;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use VendWeave\Gateway\Events\PaymentVerified;

class MarkOrderAsPaid
{
    public function handle(PaymentVerified $event): void
    {
        // Order খুঁজুন
        $order = Order::find($event->orderId);
        
        if (!$order) {
            Log::error('Order not found', ['order_id' => $event->orderId]);
            return;
        }
        
        // Order update করুন
        $order->update([
            'status' => 'paid',
            'trx_id' => $event->verificationResult->getTransactionId(),
            'paid_at' => now(),
        ]);
        
        Log::info('Order marked as paid', [
            'order_id' => $order->id,
            'trx_id' => $event->verificationResult->getTransactionId(),
        ]);
        
        // ✅ এখানে আপনি অন্যান্য কাজ করতে পারেন:
        // - Email পাঠান
        // - SMS পাঠান
        // - Inventory update করুন
        // - Invoice generate করুন
    }
}
```

### ফাইল: `app/Listeners/HandleFailedPayment.php`

```php
<?php

namespace App\Listeners;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use VendWeave\Gateway\Events\PaymentFailed;

class HandleFailedPayment
{
    public function handle(PaymentFailed $event): void
    {
        // Order খুঁজুন
        $order = Order::find($event->orderId);
        
        if ($order) {
            $order->update(['status' => 'failed']);
        }
        
        // Error log করুন
        Log::warning('Payment failed', [
            'order_id' => $event->orderId,
            'error_code' => $event->verificationResult->getErrorCode(),
            'error_message' => $event->verificationResult->getErrorMessage(),
        ]);
    }
}
```

---

# 📝 STEP 9: Events Register করুন

### Laravel 11+ (AppServiceProvider ব্যবহার করুন)

#### ফাইল: `app/Providers/AppServiceProvider.php`

```php
<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use VendWeave\Gateway\Events\PaymentVerified;
use VendWeave\Gateway\Events\PaymentFailed;
use App\Listeners\MarkOrderAsPaid;
use App\Listeners\HandleFailedPayment;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // VendWeave Payment Events Register
        Event::listen(PaymentVerified::class, MarkOrderAsPaid::class);
        Event::listen(PaymentFailed::class, HandleFailedPayment::class);
    }
}
```

### Laravel 10 (EventServiceProvider ব্যবহার করুন)

#### ফাইল: `app/Providers/EventServiceProvider.php`

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use VendWeave\Gateway\Events\PaymentVerified;
use VendWeave\Gateway\Events\PaymentFailed;
use App\Listeners\MarkOrderAsPaid;
use App\Listeners\HandleFailedPayment;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        PaymentVerified::class => [
            MarkOrderAsPaid::class,
        ],
        PaymentFailed::class => [
            HandleFailedPayment::class,
        ],
    ];
}
```

---

# 🏁 STEP 10: OrderController তৈরি করুন (Success/Failed Page)

```bash
php artisan make:controller OrderController
```

### ফাইল: `app/Http/Controllers/OrderController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Payment success page
     */
    public function success(Request $request, Order $order)
    {
        return view('order.success', [
            'order' => $order,
            'trx_id' => $request->query('trx_id'),
        ]);
    }
    
    /**
     * Payment failed page
     */
    public function failed(Request $request, Order $order)
    {
        return view('order.failed', [
            'order' => $order,
            'error_code' => $request->query('error_code'),
            'error_message' => $request->query('error_message'),
        ]);
    }
}
```

### ফাইল: `resources/views/order/success.blade.php`

```html
<!DOCTYPE html>
<html>
<head>
    <title>Payment Successful</title>
</head>
<body>
    <h1>✅ Payment Successful!</h1>
    <p>Order #{{ $order->id }}</p>
    <p>Amount: ৳{{ number_format($order->total, 2) }}</p>
    <p>Transaction ID: {{ $trx_id ?? $order->trx_id }}</p>
    <p>Thank you for your payment!</p>
    
    <a href="/">Go to Home</a>
</body>
</html>
```

### ফাইল: `resources/views/order/failed.blade.php`

```html
<!DOCTYPE html>
<html>
<head>
    <title>Payment Failed</title>
</head>
<body>
    <h1>❌ Payment Failed</h1>
    <p>Order #{{ $order->id }}</p>
    <p>Error: {{ $error_message ?? 'Something went wrong' }}</p>
    
    <a href="{{ route('checkout') }}">Try Again</a>
</body>
</html>
```

---

# ✅ Setup Complete Checklist

আপনি সব ঠিকমতো করেছেন কিনা চেক করুন:

| Step | Task | Status |
|------|------|--------|
| 1 | `composer require vendweave/payment` চালিয়েছেন | ⬜ |
| 2 | `php artisan vendor:publish --tag=vendweave-config` চালিয়েছেন | ⬜ |
| 3 | `.env` ফাইলে API credentials যোগ করেছেন | ⬜ |
| 4 | Database migration করেছেন (optional) | ⬜ |
| 5 | `CheckoutController.php` তৈরি করেছেন | ⬜ |
| 6 | `routes/web.php` এ routes যোগ করেছেন | ⬜ |
| 7 | `config/vendweave.php` এ success/failed route সেট করেছেন | ⬜ |
| 8 | `MarkOrderAsPaid.php` listener তৈরি করেছেন | ⬜ |
| 9 | `HandleFailedPayment.php` listener তৈরি করেছেন | ⬜ |
| 10 | Events register করেছেন (AppServiceProvider/EventServiceProvider) | ⬜ |
| 11 | `OrderController.php` তৈরি করেছেন | ⬜ |
| 12 | Success/Failed blade views তৈরি করেছেন | ⬜ |

---

# 🔄 Payment Flow কিভাবে কাজ করে

```
┌──────────────────────────────────────────────────────────────────┐
│                        PAYMENT FLOW                               │
├──────────────────────────────────────────────────────────────────┤
│                                                                   │
│  1. Customer আপনার checkout page এ যায়                           │
│     └── GET /checkout                                             │
│                                                                   │
│  2. Payment method select করে "Pay Now" click করে               │
│     └── POST /checkout (CheckoutController@process)               │
│                                                                   │
│  3. VendWeaveHelper::preparePayment() কল হয়                      │
│     └── Order তৈরি হয়, Reference generate হয়, POS এ reserve হয়  │
│                                                                   │
│  4. Customer VendWeave Verify Page এ redirect হয়                 │
│     └── GET /vendweave/verify/{order_id}                         │
│     └── এই page SDK দেয়, আপনাকে বানাতে হবে না!                    │
│                                                                   │
│  5. Customer bKash/Nagad app এ payment করে                       │
│     └── Reference number দিয়ে Send Money করে                     │
│                                                                   │
│  6. SDK automatically POS থেকে verify করে                        │
│     └── প্রতি 2.5 সেকেন্ডে poll করে                               │
│                                                                   │
│  7. Payment confirmed হলে:                                        │
│     └── PaymentVerified event fire হয়                            │
│     └── MarkOrderAsPaid listener কাজ করে                         │
│     └── Order status = 'paid' হয়                                 │
│                                                                   │
│  8. Customer Success page এ redirect হয়                          │
│     └── GET /order/{order}/success                               │
│                                                                   │
└──────────────────────────────────────────────────────────────────┘
```

---

# 🛣️ SDK থেকে Auto-Generated Routes

এই routes SDK automatically তৈরি করে, আপনাকে বানাতে হবে না:

| Route | Name | বিবরণ |
|-------|------|-------|
| `GET /vendweave/verify/{order}` | `vendweave.verify` | Verification Page |
| `GET /vendweave/success/{order}` | `vendweave.success` | Success Page (SDK's) |
| `GET /vendweave/failed/{order}` | `vendweave.failed` | Failed Page (SDK's) |
| `GET /api/vendweave/poll/{order}` | `vendweave.poll` | AJAX Polling |

---

# ⚠️ গুরুত্বপূর্ণ নিয়ম

| নিয়ম | ব্যাখ্যা |
|------|---------|
| 🔴 Amount Exact Match | ৳960.00 ≠ ৳960.50 - exact amount পাঠাতে হবে |
| 🔴 One TRX = One Order | একই Transaction ID দুইবার ব্যবহার করা যাবে না |
| 🔴 Method Must Match | bKash সিলেক্ট করলে bKash দিয়েই পে করতে হবে |
| 🔴 Reference Required | Customer কে reference সহ payment করতে হবে |

---

# 🐛 Troubleshooting

| সমস্যা | সমাধান |
|--------|--------|
| "INVALID_CREDENTIALS" | `.env` তে API Key/Secret চেক করুন |
| "STORE_MISMATCH" | `VENDWEAVE_STORE_SLUG` চেক করুন |
| "AMOUNT_MISMATCH" | সঠিক amount পাঠান |
| Config not found | `php artisan config:clear` চালান |
| Routes not working | `php artisan route:clear` চালান |
| Events not firing | Event register ঠিক আছে কিনা দেখুন |
| **Payment logos দেখা যাচ্ছে না** | `php artisan vendor:publish --tag=vendweave-assets --force` চালান |
| Images 404 error | `public/vendor/vendweave/images/` folder আছে কিনা দেখুন |
| **Reference দেখাচ্ছে না verify page এ** | `Session::put()` এর বদলে `VendWeaveHelper::preparePayment()` use করুন |

---

### 🔢 Reference দেখাচ্ছে না (VW3846)

**সমস্যা:** Verify page এ Reference number দেখা যাচ্ছে না।

**কারণ:** সরাসরি `Session::put()` use করলে reference generate হয় না।

**সমাধান:** `VendWeaveHelper::preparePayment()` use করুন:

**📁 File:** `app/Http/Controllers/OrderController.php`

**Step 1:** Import যুক্ত করুন:
```php
use VendWeave\Gateway\VendWeaveHelper;
```

**Step 2:** Session set করার কোড replace করুন:

```php
// ❌ এটা Remove করুন
\Session::put("vendweave_order_{$order->id}", [
    'amount' => $order->total_price,
    'payment_method' => $order->payment_method,
]);
return redirect()->route('vendweave.verify', ['order' => $order->id]);

// ✅ এটা Add করুন
$redirectUrl = VendWeaveHelper::preparePayment(
    orderId: (string) $order->id,
    amount: $order->total_price,
    paymentMethod: $order->payment_method
);
return redirect($redirectUrl);
```

**Result:** এখন verify page এ reference (VW3846) দেখাবে! ✅

---

### 🖼️ Payment Gateway Logos সমস্যা

যদি checkout page এ payment gateway logos না দেখায়:

```bash
# Assets publish করুন
php artisan vendor:publish --tag=vendweave-assets --force

# Verify করুন
ls public/vendor/vendweave/images/
# Output: vendweave-bkash.png vendweave-nagad.png vendweave-rocket.png vendweave-upay.png
```

Browser এ test করুন: `http://yoursite.com/vendor/vendweave/images/vendweave-bkash.png`

---

# 📝 Logging

Debug করার জন্য `.env` তে:

```env
VENDWEAVE_LOGGING=true
```

Log দেখতে:

```bash
tail -f storage/logs/laravel.log | grep VendWeave
```

---

# 🎉 Congratulations!

আপনি VendWeave Payment Gateway সফলভাবে integrate করেছেন!

## Test করুন:

1. `/checkout` page এ যান
2. Payment method select করুন
3. "Pay Now" click করুন
4. Verify page এ আপনার phone number দেখা যাবে
5. bKash/Nagad app থেকে reference সহ payment করুন
6. Payment confirm হলে success page এ redirect হবে

---

**Happy Coding! 🚀**
