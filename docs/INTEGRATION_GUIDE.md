# VendWeave Laravel Integration Guide

একটি সম্পূর্ণ step-by-step guide VendWeave Payment Gateway integrate করার জন্য।

---

## 📋 Prerequisites

- Laravel 10.x বা 11.x
- PHP 8.1+
- Composer
- VendWeave POS account (API Key, Secret, Store Slug)

---

## 🗂️ Database Requirements

তোমার `orders` table এ নিম্নলিখিত fields থাকা উচিত:

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->decimal('total', 10, 2);         // Payment amount
    $table->string('payment_method');         // bkash/nagad/rocket/upay
    $table->string('status')->default('pending');
    $table->string('trx_id')->nullable();     // Transaction ID from POS
    $table->timestamps();
});
```

> 💡 **Tip**: যদি তোমার field names আলাদা হয়, দেখো [Field Mapping Guide](FIELD_MAPPING.md)

---

## ⚡ Installation

### Step 1: Install via Composer

```bash
composer require vendweave/payment
```

> 📝 **Upgrading from v1.0.0?** See [CHANGELOG.md](../CHANGELOG.md) migration guide.

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=vendweave-config
```

এটা `config/vendweave.php` তৈরি করবে।

---

## ⚙️ Environment Setup

### Critical: API Credential Types

> ⚠️ **Common Mistake Alert**: Using wrong API credentials causes 401 Unauthorized error!

#### Step 1: Get Correct Credentials

1. Log into [VendWeave Dashboard](https://vendweave.com/dashboard)
2. Navigate to: **Settings** → **API Credentials**
3. Look for the section based on your integration:

| Integration Type          | Use This Section          | Status          |
| ------------------------- | ------------------------- | --------------- |
| 🌐 Laravel/Website        | "General API Credentials" | ✅ **CORRECT**  |
| 🌐 Laravel/Website        | "Website API Keys"        | ✅ **CORRECT**  |
| 📱 Android SMS App        | "Manual Payment API Keys" | ✅ For App Only |
| ❌ Laravel using "Manual" | "Manual Payment API Keys" | ❌ **WRONG**    |

> ❌ **NEVER use "Manual Payment API Keys" for Laravel integration!**

#### Step 2: Add to `.env`

`.env` ফাইলে add করো:

```env
VENDWEAVE_API_KEY=your_api_key
VENDWEAVE_API_SECRET=your_api_secret
VENDWEAVE_STORE_SLUG=your_store_slug
VENDWEAVE_API_ENDPOINT=https://vendweave.com/api
```

| Variable                 | Description                | Example                     |
| ------------------------ | -------------------------- | --------------------------- |
| `VENDWEAVE_API_KEY`      | তোমার API Key              | `vw_live_xxxx`              |
| `VENDWEAVE_API_SECRET`   | তোমার API Secret           | `secret_xxxx`               |
| `VENDWEAVE_STORE_SLUG`   | তোমার Store এর unique slug | `my-fashion-store`          |
| `VENDWEAVE_API_ENDPOINT` | POS API URL                | `https://vendweave.com/api` |

---

## 🛒 Checkout Integration

### CheckoutController.php

```php
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CheckoutController extends Controller
{
    public function checkout(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'total' => 'required|numeric|min:1',
            'payment_method' => 'required|in:bkash,nagad,rocket,upay',
        ]);

        // Create order
        $order = Order::create([
            'total' => $validated['total'],
            'payment_method' => $validated['payment_method'],
            'status' => 'pending',
        ]);

        // Store order data in session for verification page
        Session::put("vendweave_order_{$order->id}", [
            'amount' => $order->total,
            'payment_method' => $order->payment_method,
        ]);

        // Redirect to VendWeave verification page
        return redirect()->route('vendweave.verify', ['order' => $order->id]);
    }
}
```

---

## 📄 Verification Page

User যাবে:

```
/vendweave/verify/{order_id}
```

এই পেজে:

| Feature      | Description                            |
| ------------ | -------------------------------------- |
| 💰 Amount    | Order amount দেখাবে                    |
| 💳 Method    | Payment method (bKash/Nagad etc.)      |
| 📝 TRX Input | Manual transaction ID input (optional) |
| ⏱️ Timer     | 5 minute countdown timer               |
| 🔄 Auto-Poll | প্রতি 2.5 সেকেন্ডে POS API poll করবে   |

---

## ✅ Payment Success Handling

### Option 1: Using Facade

```php
use VendWeave\Gateway\Facades\VendWeave;

$result = VendWeave::verify($orderId, $amount, $paymentMethod);

if ($result->isConfirmed()) {
    $order->update([
        'status' => 'paid',
        'trx_id' => $result->getTrxId(),
    ]);

    // Send confirmation email, update inventory, etc.
}

if ($result->isFailed()) {
    $errorCode = $result->getErrorCode();
    $errorMessage = $result->getErrorMessage();
    // Log error, notify admin
}
```

### Option 2: Using Events (Recommended)

Create listener in `app/Listeners/MarkOrderAsPaid.php`:

```php
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
                'trx_id' => $event->getTrxId(),
            ]);

            // Additional actions:
            // - Send email
            // - Update inventory
            // - Create invoice
        }
    }
}
```

Register in `EventServiceProvider.php`:

```php
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

## 🔄 Payment Lifecycle

```
┌──────────────┐
│   Checkout   │  User creates order
└──────┬───────┘
       ↓
┌──────────────┐
│ Verify Page  │  User sees payment instructions
└──────┬───────┘
       ↓
┌──────────────┐
│  User Pays   │  User pays via bKash/Nagad app
└──────┬───────┘
       ↓
┌──────────────┐
│  POS Polls   │  Package polls POS every 2.5s
└──────┬───────┘
       ↓
┌──────────────┐
│ POS Confirm  │  POS confirms transaction
└──────┬───────┘
       ↓
┌──────────────┐
│ Order Paid   │  Order marked as paid
└──────┬───────┘
       ↓
┌──────────────┐
│ Success Page │  User sees confirmation
└──────────────┘
```

---

## 🧭 Reference Governance Engine

VendWeave SDK এখন **Reference Governance Engine** ব্যবহার করে reference replay, expiry, এবং audit tracking নিশ্চিত করে।

### ✅ Lifecycle

```
RESERVED → MATCHED → REPLAYED / CANCELLED → EXPIRED
```

- **RESERVED**: order এর জন্য reference reserve হয়
- **MATCHED**: POS payment reference match হলে
- **REPLAYED**: match হওয়ার পরে duplicate attempt ধরা পড়লে
- **CANCELLED**: match হওয়ার আগেই cancel হলে
- **EXPIRED**: নির্দিষ্ট সময়ের মধ্যে match না হলে

### 🛡️ Replay Prevention

Reference একবার **MATCHED** হলে পরের attempt স্বয়ংক্রিয়ভাবে block হবে এবং replay error দিবে।

### ⏱️ Expiry Command

Expiry চালাতে:

```bash
php artisan vendweave:expire-references
```

### 📊 Analytics & Audit Trail

সব গুরুত্বপূর্ণ log field থাকবে:

- `reference`
- `status`
- `order_id`
- `store_id`
- `expires_at`
- `matched_at`
- `replay_count`

এই data analytics, reconciliation, এবং audit trail এ কাজে লাগবে।

---

## 🎨 Custom Success/Failure Routes

```php
// config/vendweave.php

'callbacks' => [
    'success_route' => 'shop.order.complete',  // Your success route name
    'failed_route' => 'shop.order.failed',     // Your failure route name
],
```

---

## 🛣️ Available Routes

| Route                          | Name                  | Method | Description       |
| ------------------------------ | --------------------- | ------ | ----------------- |
| `/vendweave/verify/{order}`    | `vendweave.verify`    | GET    | Verification page |
| `/vendweave/success/{order}`   | `vendweave.success`   | GET    | Success page      |
| `/vendweave/failed/{order}`    | `vendweave.failed`    | GET    | Failure page      |
| `/vendweave/cancelled/{order}` | `vendweave.cancelled` | GET    | Cancelled page    |
| `/api/vendweave/poll/{order}`  | `vendweave.poll`      | POST   | AJAX polling      |
| `/api/vendweave/health`        | `vendweave.health`    | GET    | Health check      |

---

## 🔧 Helper Class

```php
use VendWeave\Gateway\VendWeaveHelper;

// Prepare payment and get verification URL
$url = VendWeaveHelper::preparePayment($orderId, $amount, 'bkash');
return redirect($url);

// Get available payment methods
$methods = VendWeaveHelper::getPaymentMethods();
// Returns: ['bkash' => [...], 'nagad' => [...], ...]

// Validate a payment method
if (VendWeaveHelper::isValidPaymentMethod('nagad')) {
    // Valid method
}

// Clear order data from session
VendWeaveHelper::clearOrderData($orderId);
```

---

## ⚠️ Important Rules

> 🔴 **Rule 1**: Laravel কখনো payment decide করে না। VendWeave POS সবসময় authority।

> 🔴 **Rule 2**: Amount exact match হতে হবে। ৳960.00 ≠ ৳960.50

> 🔴 **Rule 3**: একই Transaction ID দুইবার ব্যবহার করা যাবে না।

> 🔴 **Rule 4**: Store slug match না হলে transaction reject হবে।

---

## 🐛 Troubleshooting

| Problem                     | Solution                                 |
| --------------------------- | ---------------------------------------- |
| "INVALID_CREDENTIALS" error | Check `.env` API Key and Secret          |
| "STORE_MISMATCH" error      | Verify `VENDWEAVE_STORE_SLUG` is correct |
| "AMOUNT_MISMATCH" error     | Ensure order amount matches exactly      |
| Polling not working         | Check JavaScript console for errors      |
| Session data missing        | Verify session middleware is active      |

---

## 📝 Logging

Enable logging in `.env`:

```env
VENDWEAVE_LOGGING=true
VENDWEAVE_LOG_CHANNEL=stack
```

View logs:

```bash
tail -f storage/logs/laravel.log | grep VendWeave
```

---

**Happy Coding! 🚀**
