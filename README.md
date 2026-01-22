# VendWeave Laravel Payment SDK

The official, production-ready Laravel SDK for the VendWeave POS Manual Payment Gateway. Seamlessly verify bKash, Nagad, Rocket, and Upay transactions by syncing directly with your VendWeave POS store.

[![Version](https://img.shields.io/badge/version-1.9.1-blue.svg)](COMPOSER.json)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

---

## 🚀 Getting Started

To use this SDK, you must have an active store on VendWeave.

**👉 [Create your Store Account](https://vendweave.com/register-store)** (Free test mode available)

---

## ✨ Key Features

- **🔒 Store Isolation:** Each transaction is verified against your specific Store Slug, ensuring cross-store security.
- **⚡ Real-time Verification:** Uses smart polling (every 2.5s) to detect payment confirmation instantly.
- **💰 Exact Amount Matching:** Zero-tolerance verification prevents partial payment fraud.
- **🎨 Configurable UI:** Built-in, responsive verification page (Mobile First) that adapts to your theme.
- **🔌 Auto-Adaptation:** Automatically detects your Order model structure and adapts accordingly.
- **🛡️ Graceful Degradation:** Smartly handles API variations and missing fields without breaking the flow.
- **🏅 Certification Badges:** Official badges to prove your integration meets VendWeave standards.

---

## 📦 Installation

Requires PHP 8.1+ and Laravel 10 or 11.

```bash
composer require vendweave/payment
```

Recommended Composer constraint:

```
vendweave/payment: ^1.9
```

---

## ⚙️ Configuration

### 1. Publish Configuration

Publish the package configuration to `config/vendweave.php`:

```bash
php artisan vendor:publish --tag=vendweave-config
```

### 2. Set Credentials

Add your store credentials to your `.env` file. You can find these in your **[VendWeave Dashboard](https://vendweave.com/dashboard) > Settings > API Credentials**.

```env
# Required Credentials
VENDWEAVE_API_KEY=your_general_api_key
VENDWEAVE_API_SECRET=your_general_api_secret
VENDWEAVE_STORE_SLUG=your_unique_store_slug
VENDWEAVE_API_ENDPOINT=https://vendweave.com/api

# Optional: Disable SSL check for Localhost ONLY
VENDWEAVE_VERIFY_SSL=true
```

### 3. Configure Payment Numbers

Display your personal or merchant numbers on the verification page. These are editable in `.env`:

```env
VENDWEAVE_BKASH_NUMBER="017XXXXXXXX"
VENDWEAVE_NAGAD_NUMBER="018XXXXXXXX"
VENDWEAVE_ROCKET_NUMBER="019XXXXXXXX"
VENDWEAVE_UPAY_NUMBER="016XXXXXXXX"
```

---

## 🛠 Integration Guide

### Step 1: Handle Checkout

When a user places an order, create the order in your database and verify it using VendWeave.

```php
use Illuminate\Support\Facades\Session;
use App\Models\Order;

public function store(Request $request)
{
    // 1. Create Order (Status: pending)
    $order = Order::create([
        'total' => 1250.00,
        'status' => 'pending',
        'payment_method' => $request->payment_method, // e.g., 'bkash'
    ]);

    // 2. Store Verification Data in Session
    // This allows the SDK to access order details without passing sensitive data in URLs
    Session::put("vendweave_order_{$order->id}", [
        'amount' => $order->total,
        'payment_method' => $order->payment_method,
    ]);

    // 3. Redirect to VendWeave Verification Page
    return redirect()->route('vendweave.verify', ['order' => $order->id]);
}
```

### Step 2: Listen for Results

The SDK fires events when a payment is **Verified** or **Failed**. Register these listeners in your `EventServiceProvider`.

**File:** `app/Providers/EventServiceProvider.php`

```php
use VendWeave\Gateway\Events\PaymentVerified;
use VendWeave\Gateway\Events\PaymentFailed;
use App\Listeners\OnPaymentSuccess;
use App\Listeners\OnPaymentFailed;

protected $listen = [
    PaymentVerified::class => [OnPaymentSuccess::class],
    PaymentFailed::class   => [OnPaymentFailed::class],
];
```

### Step 3: Update Order Status

Create the listener to update your database.

**File:** `app/Listeners/OnPaymentSuccess.php`

```php
public function handle(PaymentVerified $event)
{
    $order = $event->order;
    $result = $event->verificationResult;

    // Update your order
    $order->update([
        'status' => 'paid',
        'transaction_id' => $result->getTransactionId(), // Get TRX ID from POS
        'paid_at' => now(),
    ]);

    // Send email, clear cart, etc.
}
```

---

## 🎨 advanced Configuration

### Custom Instructions

You can modify the user-facing text for each payment method in `config/vendweave.php`.

```php
'payment_methods' => [
    'bkash' => [
        'number' => env('VENDWEAVE_BKASH_NUMBER'),
        'type' => 'merchant',
        'instruction' => 'Go to Payment option in bKash App and enter Counter 1.',
    ],
],
```

### Customizing Views

If you need to completely overhaul the UI, you can publish the views:

```bash
php artisan vendor:publish --tag=vendweave-views
```

---

## 🚨 Troubleshooting & Error Codes

| Error Code         | Meaning                  | Solution                                                                 |
| :----------------- | :----------------------- | :----------------------------------------------------------------------- |
| `METHOD_MISMATCH`  | Payment method mismatch  | Ensure user selected the same method (bKash) that they paid with.        |
| `AMOUNT_MISMATCH`  | Amount mismatch          | The paid amount must match the order total **exactly**.                  |
| `STORE_MISMATCH`   | Wrong Store Slug         | Check `VENDWEAVE_STORE_SLUG` in your `.env`.                             |
| `TRANSACTION_USED` | Transaction already used | Each Transaction ID can only be used once.                               |
| `401 Unauthorized` | Invalid Credentials      | Use "General API Credentials" from dashboard, NOT "Manual Payment Keys". |

---

## 🧭 Reference Governance Engine

The SDK includes a **Reference Governance Engine** to protect each order reference from replay abuse and to provide auditability.

### Lifecycle

References move through a strict lifecycle to prevent reuse:

**RESERVED → MATCHED → REPLAYED / CANCELLED → EXPIRED**

- **RESERVED**: A reference is reserved for a specific order.
- **MATCHED**: A POS transaction is matched to the reference.
- **REPLAYED**: A duplicate attempt was detected after match.
- **CANCELLED**: Reference invalidated before match.
- **EXPIRED**: Time window exceeded without a match.

### Replay Prevention

Once a reference is **MATCHED**, further attempts are blocked and return replay errors. This prevents double spending and repeated confirmations.

### Expiry & Scheduler

References auto-expire after the configured TTL. Use the scheduler command to mark them as **EXPIRED**:

```
php artisan vendweave:expire-references
```

### Analytics & Audit Trail

Reference activity is logged with fields like:

- `reference`
- `status`
- `order_id`
- `store_id`
- `expires_at`
- `matched_at`
- `replay_count`

These logs support analytics, reconciliation, and audit readiness.

## 📜 License

MIT License. See [LICENSE](LICENSE) for details.
