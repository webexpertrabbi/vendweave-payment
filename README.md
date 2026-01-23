# VendWeave Laravel Payment SDK

[![Latest Version](https://img.shields.io/packagist/v/vendweave/payment.svg)](https://packagist.org/packages/vendweave/payment)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net/)
[![Laravel Version](https://img.shields.io/badge/laravel-10.x%20%7C%2011.x%20%7C%2012.x-FF2D20.svg)](https://laravel.com/)

বাংলাদেশি মোবাইল পেমেন্ট গেটওয়ে (bKash, Nagad, Rocket, Upay) এর জন্য Laravel SDK।

---

## ⚡ ৩ মিনিটে ইনস্টল

### Step 1: Composer Install

```bash
composer require vendweave/payment
```

### Step 2: Config Publish

```bash
php artisan vendor:publish --tag=vendweave-config
```

### Step 3: .env সেটআপ

```env
VENDWEAVE_API_KEY=your_api_key
VENDWEAVE_API_SECRET=your_api_secret
VENDWEAVE_STORE_SLUG=your_store_slug
VENDWEAVE_API_ENDPOINT=https://vendweave.com/api

# পেমেন্ট নম্বর (Verification page এ দেখাবে)
VENDWEAVE_BKASH_NUMBER="017XXXXXXXX"
VENDWEAVE_NAGAD_NUMBER="018XXXXXXXX"
VENDWEAVE_ROCKET_NUMBER="019XXXXXXXX"
VENDWEAVE_UPAY_NUMBER="016XXXXXXXX"
```

**ব্যস! ইনস্টল কমপ্লিট! 🎉**

---

## 🔄 পেমেন্ট ফ্লো

```
┌─────────────────┐
│   Checkout Page │  ← ইউজার পেমেন্ট মেথড বাছাই করে
└────────┬────────┘
         ↓
┌─────────────────┐
│   Verify Page   │  ← পেমেন্ট ইনস্ট্রাকশন দেখায়
└────────┬────────┘
         ↓
┌─────────────────┐
│   User Pays     │  ← ইউজার bKash/Nagad অ্যাপে পে করে
└────────┬────────┘
         ↓
┌─────────────────┐
│   Auto Verify   │  ← SDK স্বয়ংক্রিয়ভাবে verify করে
└────────┬────────┘
         ↓
┌─────────────────┐
│   Success! ✅   │  ← অর্ডার paid হয়ে যায়
└─────────────────┘
```

---

## 📖 ডকুমেন্টেশন

| ডকুমেন্ট | বিবরণ |
|----------|-------|
| [📘 Installation Guide](docs/INTEGRATION_GUIDE.md) | সম্পূর্ণ ইনস্টলেশন ও সেটআপ গাইড |
| [📋 API Contract](docs/API_CONTRACT.md) | POS API স্পেসিফিকেশন |

---

## 🛒 Quick Example

### Checkout Controller

```php
use Illuminate\Support\Facades\Session;

public function checkout(Request $request)
{
    $order = Order::create([
        'total' => 1250.00,
        'payment_method' => $request->payment_method,
        'status' => 'pending',
    ]);

    Session::put("vendweave_order_{$order->id}", [
        'amount' => $order->total,
        'payment_method' => $order->payment_method,
    ]);

    return redirect()->route('vendweave.verify', ['order' => $order->id]);
}
```

### Payment Events

```php
// EventServiceProvider.php
use VendWeave\Gateway\Events\PaymentVerified;
use VendWeave\Gateway\Events\PaymentFailed;

protected $listen = [
    PaymentVerified::class => [MarkOrderAsPaid::class],
    PaymentFailed::class   => [HandleFailedPayment::class],
];
```

---

## ❌ Error Codes

| Code | অর্থ | সমাধান |
|------|------|--------|
| `METHOD_MISMATCH` | ভুল পেমেন্ট মেথড | সিলেক্টেড মেথড দিয়ে পে করুন |
| `AMOUNT_MISMATCH` | Amount ম্যাচ হয়নি | সঠিক amount পাঠান |
| `STORE_MISMATCH` | স্টোর ম্যাচ হয়নি | `.env` তে store slug চেক করুন |
| `TRANSACTION_USED` | TRX আগেই ব্যবহৃত | প্রতিটি TRX একবারই ব্যবহার হয় |

---

## 📜 License

MIT License. See [LICENSE](LICENSE) for details.
