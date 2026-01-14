# VendWeave Laravel Payment SDK

VendWeave একটি production-grade Laravel payment SDK, যা VendWeave POS infrastructure ব্যবহার করে নিরাপদভাবে payment verification সম্পন্ন করে।

এই SDK **bKash, Nagad, Rocket এবং Upay** সমর্টন করে এবং **আপনার সিস্টেমের সাথে auto-adapt** করে।

---

## 🚀 Features

| Feature                  | Description                                |
| ------------------------ | ------------------------------------------ |
| 🔐 Secure Authentication | API Key + Secret based authentication      |
| 🏪 Store Isolation       | Store-scoped transaction verification      |
| 💰 Exact Amount Match    | Zero tolerance - amount must match exactly |
| ⚡ Real-time Polling     | Auto-polling every 2.5 seconds             |
| 🎨 Fintech UI            | Dark theme, mobile-first verification page |
| 🚦 Rate Limiting         | Built-in protection against abuse          |
| 🧩 Laravel Native        | Works with Laravel 10 & 11                 |
| 🧾 POS Authority         | POS is single source of truth              |
| 🤖 Auto-Adaptation       | SDK adapts to your DB structure            |
| 🔄 Smart Normalization   | Handles API response variations            |

---

## 💳 Supported Payment Methods

| Method | Status       |
| ------ | ------------ |
| bKash  | ✅ Supported |
| Nagad  | ✅ Supported |
| Rocket | ✅ Supported |
| Upay   | ✅ Supported |

---

## ⚡ Quick Start (5 Minutes)

### Step 1: Install Package

```bash
composer require vendweave/payment
```

### Step 3: Get Your API Credentials

> ⚠️ **CRITICAL**: Use the correct API credential type!

#### For Laravel/Website Integration:

1. Log into your [VendWeave Dashboard](https://vendweave.com/dashboard)
2. Go to **Settings** → **API Credentials**
3. Use **"General API Credentials"** or **"Website API Keys"**
4. ❌ **DO NOT USE** "Manual Payment API Keys" (those are for Android app only)

#### Common Mistake:

- ❌ Using "Manual Payment API Keys" → Results in **401 Unauthorized** error
- ✅ Using "General/Website API Keys" → Correct for Laravel

### Step 4: Add Environment Variables

```bash
php artisan vendor:publish --tag=vendweave-config
```

### Step 3: Add Environment Variables

```env
VENDWEAVE_API_KEY=your_api_key
VENDWEAVE_API_SECRET=your_api_secret
VENDWEAVE_STORE_SLUG=your_store_slug
VENDWEAVE_API_ENDPOINT=https://vendweave.com/api
```

### Step 4: Redirect to Verify Page

```php
use Illuminate\Support\Facades\Session;

// After creating order, store data in session
Session::put("vendweave_order_{$order->id}", [
    'amount' => $order->total,
    'payment_method' => 'bkash',
]);

// Redirect to verify page
return redirect()->route('vendweave.verify', ['order' => $order->id]);
```

**Done!** User will see the verification page and payment will be auto-verified.

---

## 📖 সম্পূর্ণ ইন্টিগ্রেশন গাইড (A to Z)

### ধাপ ১: প্যাকেজ ইনস্টল করুন

```bash
composer require vendweave/payment
```

### ধাপ ২: Config Publish করুন

```bash
php artisan vendor:publish --tag=vendweave-config
```

### ধাপ ৩: Environment Variables সেটআপ

#### ৩.১ VendWeave Credentials নিন

1. [VendWeave Dashboard](https://vendweave.com/dashboard) এ লগইন করুন
2. **Settings** → **API Credentials** এ যান
3. **"General API Credentials"** বা **"Website API Keys"** copy করুন

> ⚠️ **সতর্কতা**: "Manual Payment API Keys" ব্যবহার করবেন না - সেগুলো Android app এর জন্য!

#### ৩.২ `.env` ফাইলে যোগ করুন

```env
# VendWeave Payment Gateway
VENDWEAVE_API_KEY=your_api_key_here
VENDWEAVE_API_SECRET=your_api_secret_here
VENDWEAVE_STORE_SLUG=your-store-slug
VENDWEAVE_API_ENDPOINT=https://vendweave.com/api

# Local development এ SSL error এড়াতে (Production এ false করবেন না!)
VENDWEAVE_VERIFY_SSL=true
```

### ধাপ ৪: Database Migration চালান

SDK এর নিজস্ব কোনো migration নেই, তবে আপনার `orders` table এ এই columns থাকতে হবে:

```sql
-- প্রয়োজনীয় columns (আপনার existing structure অনুযায়ী)
id              -- Order ID
total           -- মোট টাকা
payment_method  -- bkash/nagad/rocket/upay
status          -- pending/paid/failed
trx_id          -- Transaction ID (nullable)
```

### ধাপ ৫: Order Model Configure করুন

#### Option A: সরাসরি columns থাকলে

যদি আপনার `orders` table এ সরাসরি `payment_method`, `total`, `trx_id` columns থাকে:

```php
// app/Models/Order.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'total',
        'payment_method',
        'status',
        'trx_id',
        // ... অন্যান্য fields
    ];
}
```

#### Option B: Separate Payment Table থাকলে

যদি payment data আলাদা `payments` table এ থাকে:

```php
// app/Models/Order.php
class Order extends Model
{
    protected $fillable = ['total', 'status'];

    // Payment relation
    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    // ⚠️ গুরুত্বপূর্ণ: Eager load করুন
    protected $with = ['payment'];

    // Accessors for VendWeave
    protected $appends = ['payment_method', 'trx_id'];

    public function getPaymentMethodAttribute()
    {
        return $this->payment?->method ?? 'bkash';
    }

    public function getTrxIdAttribute()
    {
        return $this->payment?->transaction_id;
    }
}
```

### ধাপ ৬: Routes Configure করুন

Routes already included হয়ে যাবে automatically। চেক করতে:

```bash
php artisan route:list --name=vendweave
```

**Available Routes:**

- `GET /vendweave/verify/{order}` - Verification page
- `GET /vendweave/poll/{order}` - Auto-polling endpoint
- `GET /vendweave/success/{order}` - Success redirect
- `GET /vendweave/failed/{order}` - Failed redirect
- `GET /vendweave/cancel/{order}` - Cancel redirect

### ধাপ ৭: Checkout Integration

আপনার checkout controller এ:

```php
use Illuminate\Support\Facades\Session;

public function checkout(Request $request)
{
    // 1. Order তৈরি করুন
    $order = Order::create([
        'user_id' => auth()->id(),
        'total' => 500.00,
        'status' => 'pending',
        'payment_method' => $request->payment_method, // bkash/nagad/rocket/upay
    ]);

    // 2. Session এ order data store করুন
    Session::put("vendweave_order_{$order->id}", [
        'amount' => $order->total,
        'payment_method' => $order->payment_method,
    ]);

    // 3. VendWeave verify page এ redirect করুন
    return redirect()->route('vendweave.verify', ['order' => $order->id]);
}
```

### ধাপ ৮: Success/Failed Handling

#### Success Callback

```php
// app/Listeners/MarkOrderAsPaid.php
namespace App\Listeners;

use VendWeave\Gateway\Events\PaymentVerified;

class MarkOrderAsPaid
{
    public function handle(PaymentVerified $event)
    {
        $order = $event->order;
        $result = $event->verificationResult;

        // Order status update করুন
        $order->update([
            'status' => 'paid',
            'trx_id' => $result->getTransactionId(),
        ]);

        // অন্যান্য কাজ (email, notification, etc.)
    }
}
```

#### Failed Callback

```php
// app/Listeners/HandleFailedPayment.php
use VendWeave\Gateway\Events\PaymentFailed;

class HandleFailedPayment
{
    public function handle(PaymentFailed $event)
    {
        $order = $event->order;

        $order->update(['status' => 'failed']);

        // Log করুন বা user কে notify করুন
    }
}
```

#### Event Register করুন

```php
// app/Providers/EventServiceProvider.php
use VendWeave\Gateway\Events\PaymentVerified;
use VendWeave\Gateway\Events\PaymentFailed;

protected $listen = [
    PaymentVerified::class => [
        \App\Listeners\MarkOrderAsPaid::class,
    ],
    PaymentFailed::class => [
        \App\Listeners\HandleFailedPayment::class,
    ],
];
```

### ধাপ ৯: Testing

#### Local Development এ Test করুন

```bash
# Server চালান
php artisan serve

# Browser এ যান
http://127.0.0.1:8000/vendweave/verify/1
```

#### Test Checklist

- [ ] Verify page load হচ্ছে কি?
- [ ] Auto-polling কাজ করছে কি? (Console দেখুন)
- [ ] Payment করার পর status update হচ্ছে কি?
- [ ] Success page এ redirect হচ্ছে কি?

### ধাপ ১০: Troubleshooting

#### SSL Certificate Error (Local Development)

```env
# .env তে যোগ করুন
VENDWEAVE_VERIFY_SSL=false
```

তারপর:

```bash
php artisan config:clear
```

#### Payment Method Mismatch Error

**সমস্যা:** Order model থেকে `payment_method` পাচ্ছে না।

**সমাধান:** Order model এ accessor যোগ করুন (ধাপ ৫ দেখুন)।

#### 422 Validation Error

**সমস্যা:** POS API তে wrong parameters পাঠাচ্ছে।

**সমাধান:** SDK automatically map করে! শুধু config cache clear করুন:

```bash
php artisan config:clear
```

#### 401 Unauthorized Error

**সমস্যা:** ভুল API credentials ব্যবহার করছেন।

**সমাধান:** নিশ্চিত করুন "General API Credentials" ব্যবহার করছেন, "Manual Payment API Keys" নয়।

---

### ধাপ ১১: Payment Numbers & Instructions কনফিগার করুন

`config/vendweave.php` ফাইলে `payment_methods` সেকশনে আপনার নাম্বার এবং ইন্সট্রাকশন সেট করুন:

```php
'payment_methods' => [
    'bkash' => [
        'number' => env('VENDWEAVE_BKASH_NUMBER', '01XXXXXXXXX'),
        'type' => 'personal',
        'instruction' => 'এই নাম্বারে টাকা পাঠিয়ে ভেরিফাই করুন।',
    ],
    // ... অন্যান্য মেথড
],
```

এবং `.env` ফাইলে নাম্বারগুলো সেট করুন:

```env
VENDWEAVE_BKASH_NUMBER=01700000000
VENDWEAVE_NAGAD_NUMBER=01600000000
VENDWEAVE_U_PAY_NUMBER=01800000000
```

Production এ deploy করার আগে:

- [ ] `VENDWEAVE_VERIFY_SSL=true` set করুন
- [ ] সঠিক API credentials ব্যবহার করছেন
- [ ] `APP_ENV=production` এবং `APP_DEBUG=false`
- [ ] Config cache করুন: `php artisan config:cache`
- [ ] Route cache করুন: `php artisan route:cache`
- [ ] Events properly registered আছে
- [ ] Database indexes আছে `orders` table এ
- [ ] Logging enable আছে errors track করতে

---

## 🏗 Architecture

```
┌─────────────────┐     ┌─────────────────────┐     ┌─────────────────┐
│   Laravel App   │ ──► │  VendWeave Package  │ ──► │  VendWeave POS  │
│   (Your Shop)   │     │   (This Package)    │     │   (Authority)   │
└─────────────────┘     └─────────────────────┘     └─────────────────┘
```

> ⚠️ **Important**: Laravel কখনো নিজে payment success সিদ্ধান্ত নেয় না।  
> VendWeave POS সবসময় authority।

---

## 🔁 Payment Flow

```
1. User Checkout
      ↓
2. Select Payment Method (bKash/Nagad/Rocket/Upay)
      ↓
3. Redirect to Verify Page (/vendweave/verify/{order})
      ↓
4. User Pays via Mobile App
      ↓
5. Package Polls POS API (every 2.5s)
      ↓
6. POS Confirms → Order Marked Paid
      ↓
7. Redirect to Success Page
```

---

## 🛣️ Routes

| Route                         | Name                | Description               |
| ----------------------------- | ------------------- | ------------------------- |
| `/vendweave/verify/{order}`   | `vendweave.verify`  | Payment verification page |
| `/vendweave/success/{order}`  | `vendweave.success` | Payment success page      |
| `/vendweave/failed/{order}`   | `vendweave.failed`  | Payment failed page       |
| `/api/vendweave/poll/{order}` | `vendweave.poll`    | AJAX polling endpoint     |

---

## 🚨 Error Codes

| Error Code                 | Description                            | Action                         |
| -------------------------- | -------------------------------------- | ------------------------------ |
| `TRANSACTION_NOT_FOUND`    | No matching transaction found          | User needs to complete payment |
| `AMOUNT_MISMATCH`          | Amount doesn't match                   | Check order total              |
| `METHOD_MISMATCH`          | Payment method doesn't match           | Verify method selected         |
| `STORE_MISMATCH`           | Transaction belongs to different store | Security violation             |
| `TRANSACTION_ALREADY_USED` | TRX ID already used                    | Possible fraud                 |
| `TRANSACTION_EXPIRED`      | Transaction too old                    | Timeout - retry payment        |
| `INVALID_CREDENTIALS`      | API key/secret invalid                 | Check .env configuration       |

---

## 🔐 Security Features

- ✅ **API Authentication**: Every request requires API Key + Secret
- ✅ **Store Isolation**: Transactions validated against store_slug
- ✅ **Exact Amount**: No tolerance - prevents partial payment fraud
- ✅ **No Reuse**: Transaction IDs cannot be used twice
- ✅ **Rate Limiting**: 60 requests/minute per order
- ✅ **Logging**: All API calls logged (configurable)

---

## 📚 Documentation

| Document                                       | Description                          |
| ---------------------------------------------- | ------------------------------------ |
| [Integration Guide](docs/INTEGRATION_GUIDE.md) | Step-by-step Laravel integration     |
| [Field Mapping](docs/FIELD_MAPPING.md)         | Map your DB fields to package fields |
| [API Contract](docs/API_CONTRACT.md)           | POS API specification                |
| [Website Copy](docs/WEBSITE_COPY.md)           | Marketing copy for your website      |

---

## 🧩 Facade Usage

```php
use VendWeave\Gateway\Facades\VendWeave;

// Verify a transaction
$result = VendWeave::verify($orderId, $amount, 'bkash');

if ($result->isConfirmed()) {
    // Payment successful!
    $trxId = $result->getTrxId();
}

// Check payment methods
$methods = VendWeave::getPaymentMethods();

// Validate method
VendWeave::isValidPaymentMethod('nagad'); // true
```

---

## 🎯 Events

Listen to payment events in `EventServiceProvider`:

```php
use VendWeave\Gateway\Events\PaymentVerified;
use VendWeave\Gateway\Events\PaymentFailed;

protected $listen = [
    PaymentVerified::class => [
        \App\Listeners\MarkOrderAsPaid::class,
    ],
    PaymentFailed::class => [
        \App\Listeners\HandleFailedPayment::class,
    ],
];
```

---

## ⚙️ Configuration Options

```php
// config/vendweave.php

'polling' => [
    'interval_ms' => 2500,      // Poll every 2.5 seconds
    'max_attempts' => 120,       // Max 120 attempts (5 minutes)
    'timeout_seconds' => 300,    // Overall timeout
],

'rate_limit' => [
    'max_attempts' => 60,        // 60 requests per minute
    'decay_minutes' => 1,
],
```

---

## 🔄 প্যাকেজ আপডেট করার নিয়ম

### নতুন ইনস্টলেশন

```bash
composer require vendweave/payment
```

### আগের ভার্সন থেকে আপডেট

#### v1.0.0/v1.1.0 থেকে v1.2.0 এ আপডেট:

```bash
# আগের ভার্সন থাকলে আপডেট করুন
composer update vendweave/payment

# Config ফাইল রিফ্রেশ করুন (নতুন options পেতে)
php artisan vendor:publish --tag=vendweave-config --force

# Config cache ক্লিয়ার করুন
php artisan config:clear
```

#### পুরাতন `vendweave/gateway` থেকে মাইগ্রেশন:

```bash
# পুরাতন প্যাকেজ রিমুভ করুন
composer remove vendweave/gateway

# নতুন প্যাকেজ ইনস্টল করুন
composer require vendweave/payment

# Config পুনরায় publish করুন
php artisan vendor:publish --tag=vendweave-config --force

# Cache ক্লিয়ার করুন
php artisan config:clear
php artisan cache:clear
```

### ভার্সন চেক করা

```bash
composer show vendweave/payment
```

### সর্বশেষ ভার্সন পেতে

```bash
composer update vendweave/payment --with-dependencies
```

> 💡 **Tip**: প্রতিটি আপডেটের পর [CHANGELOG.md](CHANGELOG.md) দেখুন নতুন features ও breaking changes জানতে।

---

## ✅ Production Status

| Item            | Status               |
| --------------- | -------------------- |
| Version         | **v1.1.0**           |
| Stability       | **Production Ready** |
| Laravel Support | 10.x, 11.x           |
| PHP Support     | 8.1+                 |
| Auto-Adaptation | ✅ Enabled           |

---

## 🆕 What's New in v1.1.0

- ✅ **Two-layer parameter mapping** - SDK auto-maps to POS API contract
- ✅ **Intelligent response normalization** - Handles List/Object variations
- ✅ **Graceful degradation** - Works even with incomplete API responses
- ✅ **Enhanced documentation** - Clear API credential type guidance
- ✅ **Better debugging** - Detailed logging for production issues

See [CHANGELOG.md](CHANGELOG.md) for full details.

---

## 📜 License

MIT License - See [LICENSE](LICENSE) file.

---

## 🆘 Support

For issues and feature requests, please open an issue on GitHub.

---

**VendWeave — Powering Trusted Digital Payments 🚀**
