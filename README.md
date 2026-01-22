# VendWeave Laravel Payment SDK

[![Latest Version](https://img.shields.io/packagist/v/vendweave/payment.svg)](https://packagist.org/packages/vendweave/payment)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net/)
[![Laravel Version](https://img.shields.io/badge/laravel-10.x%20%7C%2011.x%20%7C%2012.x-FF2D20.svg)](https://laravel.com/)

Protocol-aligned Laravel SDK for VendWeave POS payment verification.

---

## Installation

```bash
composer require vendweave/payment
```

```bash
php artisan vendor:publish --tag=vendweave-config
```

## Configuration

Add credentials to `.env`:

```env
VENDWEAVE_API_KEY=your_api_key
VENDWEAVE_API_SECRET=your_api_secret
VENDWEAVE_STORE_SLUG=your_store_slug
VENDWEAVE_API_ENDPOINT=https://vendweave.com/api

# Payment Numbers (displayed on verification page)
VENDWEAVE_BKASH_NUMBER="017XXXXXXXX"
VENDWEAVE_NAGAD_NUMBER="018XXXXXXXX"
```

## Quick Start

### 1. Checkout → Redirect

```php
use Illuminate\Support\Facades\Session;

public function checkout(Request $request)
{
    $order = Order::create([
        'total' => 1250.00,
        'status' => 'pending',
        'payment_method' => $request->payment_method,
    ]);

    Session::put("vendweave_order_{$order->id}", [
        'amount' => $order->total,
        'payment_method' => $order->payment_method,
    ]);

    return redirect()->route('vendweave.verify', ['order' => $order->id]);
}
```

### 2. Listen for Events

```php
// app/Providers/EventServiceProvider.php
use VendWeave\Gateway\Events\PaymentVerified;
use VendWeave\Gateway\Events\PaymentFailed;

protected $listen = [
    PaymentVerified::class => [OnPaymentSuccess::class],
    PaymentFailed::class   => [OnPaymentFailed::class],
];
```

### 3. Handle Success

```php
// app/Listeners/OnPaymentSuccess.php
public function handle(PaymentVerified $event)
{
    $event->order->update([
        'status' => 'paid',
        'transaction_id' => $event->verificationResult->getTransactionId(),
        'paid_at' => now(),
    ]);
}
```

## Error Codes

| Code | Meaning | Solution |
|------|---------|----------|
| `METHOD_MISMATCH` | Payment method mismatch | User must pay with selected method |
| `AMOUNT_MISMATCH` | Amount doesn't match | Paid amount must equal order total |
| `STORE_MISMATCH` | Wrong store | Check `VENDWEAVE_STORE_SLUG` |
| `TRANSACTION_USED` | Already used | Each TRX ID is single-use |
| `401 Unauthorized` | Invalid credentials | Use General API Credentials |

## Documentation

📖 **[Full Integration Guide](docs/INTEGRATION_GUIDE.md)** — Step-by-step setup with Laravel 12

📋 **[API Contract](docs/API_CONTRACT.md)** — POS endpoint specifications

📝 **[Release Notes](docs/RELEASE_NOTES_v1.9.1.md)** — v1.9.1 changelog

## License

MIT License. See [LICENSE](LICENSE) for details.
