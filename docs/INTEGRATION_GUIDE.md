# VendWeave Laravel Integration Guide

সম্পূর্ণ ইনস্টলেশন ও সেটআপ গাইড - Laravel 10, 11, 12 সাপোর্টেড।

---

## 📋 প্রয়োজনীয়তা

- PHP 8.1+
- Laravel 10.x / 11.x / 12.x
- Composer
- VendWeave POS অ্যাকাউন্ট (API Key, Secret, Store Slug)

---

## ⚡ ইনস্টলেশন

### Step 1: Package Install

```bash
composer require vendweave/payment
```

### Step 2: Config Publish

```bash
php artisan vendor:publish --tag=vendweave-config
```

এটি `config/vendweave.php` ফাইল তৈরি করবে।

### Step 3: Environment Setup

`.env` ফাইলে যোগ করুন:

```env
# API Credentials (VendWeave Dashboard থেকে নিন)
VENDWEAVE_API_KEY=your_api_key
VENDWEAVE_API_SECRET=your_api_secret
VENDWEAVE_STORE_SLUG=your_store_slug
VENDWEAVE_API_ENDPOINT=https://vendweave.com/api

# Payment Numbers (Verification page এ দেখাবে)
VENDWEAVE_BKASH_NUMBER="017XXXXXXXX"
VENDWEAVE_NAGAD_NUMBER="018XXXXXXXX"
VENDWEAVE_ROCKET_NUMBER="019XXXXXXXX"
VENDWEAVE_UPAY_NUMBER="016XXXXXXXX"

# Optional Settings
VENDWEAVE_VERIFY_SSL=true
VENDWEAVE_LOGGING=true
```

---

## 🗂️ Database Setup (Optional)

আপনার existing `orders` টেবিল ব্যবহার করতে পারেন। নিচের fields রাখলে ভালো হয়:

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->decimal('total', 10, 2);           // পেমেন্ট amount
    $table->string('payment_method');           // bkash/nagad/rocket/upay
    $table->string('status')->default('pending');
    $table->string('trx_id')->nullable();       // Transaction ID
    $table->string('reference')->nullable();    // Payment Reference
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();
});
```

### Custom Field Mapping

আপনার field নাম ভিন্ন হলে config এ mapping করুন:

```php
// config/vendweave.php
'order_mapping' => [
    'id' => 'order_id',
    'amount' => 'grand_total',
    'payment_method' => 'gateway',
    'status' => 'order_status',
],
```

---

## 🛒 Step-by-Step Integration

### Step 1: Checkout Form তৈরি করুন

```html
<form action="{{ route('checkout.process') }}" method="POST">
    @csrf
    
    <!-- Order Summary -->
    <div class="order-summary">
        <h3>Order Total: ৳{{ number_format($cart->total, 2) }}</h3>
    </div>
    
    <!-- Payment Method Selection -->
    <!-- 👉 সুন্দর UI এর জন্য দেখুন: docs/CHECKOUT_UI.md -->
    <div class="payment-methods">
        <label>
            <input type="radio" name="payment_method" value="bkash" required>
            bKash
        </label>
        <label>
            <input type="radio" name="payment_method" value="nagad">
            Nagad
        </label>
        <label>
            <input type="radio" name="payment_method" value="rocket">
            Rocket
        </label>
        <label>
            <input type="radio" name="payment_method" value="upay">
            Upay
        </label>
    </div>
    
    <button type="submit">Pay Now</button>
</form>
```

### Step 2: CheckoutController তৈরি করুন

```php
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CheckoutController extends Controller
{
    public function process(Request $request)
    {
        // Validate
        $validated = $request->validate([
            'payment_method' => 'required|in:bkash,nagad,rocket,upay',
        ]);

        // Create Order
        $order = Order::create([
            'user_id' => auth()->id(),
            'total' => $this->getCartTotal(), // আপনার cart total
            'payment_method' => $validated['payment_method'],
            'status' => 'pending',
        ]);

        // Session এ Order Data রাখুন (SDK এর জন্য প্রয়োজন)
        Session::put("vendweave_order_{$order->id}", [
            'amount' => $order->total,
            'payment_method' => $order->payment_method,
        ]);

        // VendWeave Verify Page এ Redirect
        return redirect()->route('vendweave.verify', ['order' => $order->id]);
    }
    
    private function getCartTotal()
    {
        // আপনার cart total logic
        return 1250.00;
    }
}
```

### Step 3: Routes যোগ করুন

```php
// routes/web.php
Route::post('/checkout', [CheckoutController::class, 'process'])->name('checkout.process');
Route::get('/order/success/{order}', [OrderController::class, 'success'])->name('order.success');
Route::get('/order/failed/{order}', [OrderController::class, 'failed'])->name('order.failed');
```

### Step 4: Success/Failed Route Config

```php
// config/vendweave.php
'callbacks' => [
    'success_route' => 'order.success',  // আপনার success route name
    'failed_route' => 'order.failed',    // আপনার failed route name
],
```

---

## ✅ Payment Events Handle করুন

### Event Listener তৈরি করুন

```php
// app/Listeners/MarkOrderAsPaid.php
<?php

namespace App\Listeners;

use App\Models\Order;
use VendWeave\Gateway\Events\PaymentVerified;

class MarkOrderAsPaid
{
    public function handle(PaymentVerified $event): void
    {
        $order = Order::find($event->orderId);

        if ($order) {
            $order->update([
                'status' => 'paid',
                'trx_id' => $event->verificationResult->getTransactionId(),
                'paid_at' => now(),
            ]);

            // Additional Actions:
            // - Email পাঠান
            // - Inventory আপডেট করুন
            // - Invoice তৈরি করুন
        }
    }
}
```

```php
// app/Listeners/HandleFailedPayment.php
<?php

namespace App\Listeners;

use App\Models\Order;
use VendWeave\Gateway\Events\PaymentFailed;
use Illuminate\Support\Facades\Log;

class HandleFailedPayment
{
    public function handle(PaymentFailed $event): void
    {
        $order = Order::find($event->orderId);

        if ($order) {
            $order->update(['status' => 'failed']);
        }

        Log::warning('[VendWeave] Payment Failed', [
            'order_id' => $event->orderId,
            'error_code' => $event->verificationResult->getErrorCode(),
            'error_message' => $event->verificationResult->getErrorMessage(),
        ]);
    }
}
```

### EventServiceProvider এ Register করুন

```php
// app/Providers/EventServiceProvider.php
use VendWeave\Gateway\Events\PaymentVerified;
use VendWeave\Gateway\Events\PaymentFailed;
use App\Listeners\MarkOrderAsPaid;
use App\Listeners\HandleFailedPayment;

protected $listen = [
    PaymentVerified::class => [
        MarkOrderAsPaid::class,
    ],
    PaymentFailed::class => [
        HandleFailedPayment::class,
    ],
];
```

---

## 🛣️ Available Routes

SDK নিজে থেকে এই routes তৈরি করে:

| Route | Name | Method | Description |
|-------|------|--------|-------------|
| `/vendweave/verify/{order}` | `vendweave.verify` | GET | Verification Page |
| `/vendweave/success/{order}` | `vendweave.success` | GET | Success Page |
| `/vendweave/failed/{order}` | `vendweave.failed` | GET | Failed Page |
| `/api/vendweave/poll/{order}` | `vendweave.poll` | GET | AJAX Polling |

---

## 🔄 Payment Lifecycle

```
┌─────────────────────────────────────────────────────────────────┐
│                      SDK Verification Flow                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│   1. reserveReference()  →  POS এ reference register             │
│            ↓                                                     │
│   2. poll()              →  POS এ payment check                  │
│            ↓                                                     │
│   3. verify()            →  TRX ID দিয়ে verify                   │
│            ↓                                                     │
│   4. confirm()           →  Transaction lock/consume             │
│            ↓                                                     │
│   5. status = 'used'     →  ✅ SUCCESS REDIRECT                  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Status Meanings

| Status | অর্থ | Frontend Action |
|--------|------|-----------------|
| `pending` | পেমেন্ট আসেনি | Polling চলবে |
| `verified` | Transaction পাওয়া গেছে | Polling চলবে |
| `confirmed` | Verify হয়েছে | Polling চলবে |
| `success` | Confirm হয়েছে | Polling চলবে |
| `used` | সম্পূর্ণ ✅ | Success Redirect |
| `failed` | ব্যর্থ | Failed Redirect |

> ⚠️ **গুরুত্বপূর্ণ:** শুধুমাত্র `used` status এ success redirect হবে!

---

## 🔧 Helper Class ব্যবহার

```php
use VendWeave\Gateway\VendWeaveHelper;

// Payment URL পেতে
$url = VendWeaveHelper::preparePayment($orderId, $amount, 'bkash');
return redirect($url);

// Available Payment Methods
$methods = VendWeaveHelper::getPaymentMethods();
// ['bkash' => [...], 'nagad' => [...], ...]

// Valid Payment Method চেক
if (VendWeaveHelper::isValidPaymentMethod('nagad')) {
    // Valid
}

// Session Data Clear
VendWeaveHelper::clearOrderData($orderId);
```

---

## ⚠️ গুরুত্বপূর্ণ নিয়ম

| নিয়ম | ব্যাখ্যা |
|------|---------|
| 🔴 Amount Exact Match | ৳960.00 ≠ ৳960.50 - exact amount পাঠাতে হবে |
| 🔴 One TRX = One Order | একই Transaction ID দুইবার ব্যবহার করা যাবে না |
| 🔴 Store Match | সঠিক store slug না হলে reject হবে |
| 🔴 Method Match | bKash সিলেক্ট করলে bKash দিয়েই পে করতে হবে |

---

## 🐛 Troubleshooting

| সমস্যা | সমাধান |
|--------|--------|
| "INVALID_CREDENTIALS" | `.env` তে API Key/Secret চেক করুন |
| "STORE_MISMATCH" | `VENDWEAVE_STORE_SLUG` চেক করুন |
| "AMOUNT_MISMATCH" | সঠিক amount পাঠান |
| Polling কাজ করছে না | Browser Console এ error দেখুন |
| Session data নেই | Session middleware active আছে কিনা দেখুন |

---

## 📝 Logging

```env
VENDWEAVE_LOGGING=true
VENDWEAVE_LOG_CHANNEL=stack
```

Log দেখতে:

```bash
tail -f storage/logs/laravel.log | grep VendWeave
```

---

## 🎨 Next Step

সুন্দর Checkout UI এর জন্য দেখুন: **[CHECKOUT_UI.md](CHECKOUT_UI.md)**

---

**Happy Coding! 🚀**
