# VendWeave Laravel Payment SDK

[![Latest Version](https://img.shields.io/packagist/v/vendweave/payment.svg)](https://packagist.org/packages/vendweave/payment)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net/)
[![Laravel Version](https://img.shields.io/badge/laravel-10.x%20%7C%2011.x%20%7C%2012.x-FF2D20.svg)](https://laravel.com/)

বাংলাদেশি মোবাইল পেমেন্ট গেটওয়ে (bKash, Nagad, Rocket, Upay) এর জন্য Laravel SDK।

---

## ⚡ Quick Install

```bash
# Package install
composer require vendweave/payment

# Config publish
php artisan vendor:publish --tag=vendweave-config

# Assets publish (payment gateway logos)
php artisan vendor:publish --tag=vendweave-assets
```

## 🔑 .env Setup

```env
VENDWEAVE_API_KEY=your_api_key
VENDWEAVE_API_SECRET=your_api_secret
VENDWEAVE_STORE_SLUG=your_store_slug
VENDWEAVE_API_ENDPOINT=https://vendweave.com/api

VENDWEAVE_BKASH_NUMBER="017XXXXXXXX"
VENDWEAVE_NAGAD_NUMBER="018XXXXXXXX"
```

## 🛒 Basic Usage

```php
use VendWeave\Gateway\VendWeaveHelper;

// Checkout এ payment process করুন
public function checkout(Request $request)
{
    $order = Order::create([
        'total' => 1250.00,
        'payment_method' => $request->payment_method,
        'status' => 'pending',
    ]);

    // VendWeave verify page এ redirect
    $url = VendWeaveHelper::preparePayment(
        orderId: (string) $order->id,
        amount: $order->total,
        paymentMethod: $order->payment_method
    );

    return redirect($url);
}
```

## 📖 Full Documentation

| ডকুমেন্ট | বিবরণ |
|----------|-------|
| 📘 **[Complete Integration Guide](docs/INTEGRATION_GUIDE.md)** | Step-by-step সম্পূর্ণ গাইড |
| 📋 [API Contract](docs/API_CONTRACT.md) | POS API স্পেসিফিকেশন |

---

## 🎯 আপনাকে যা যা করতে হবে

| Task | বিবরণ |
|------|-------|
| ✅ Install & Configure | Package install, .env setup |
| ✅ Checkout Page | নিজে বানান (payment method select) |
| ✅ CheckoutController | Order create → VendWeave redirect |
| ✅ Event Listeners | PaymentVerified, PaymentFailed handle |
| ✅ Success/Failed Pages | নিজে বানান |

## 🎁 SDK যা যা দেয়

| Feature | বিবরণ |
|---------|-------|
| 🔐 Verify Page | Auto-generated polling UI |
| 🔄 Auto Polling | POS থেকে payment status check |
| 📣 Events | PaymentVerified, PaymentFailed |
| 🛡️ Validation | Amount, method, store matching |

---

## ❌ Error Codes

| Code | অর্থ |
|------|------|
| `METHOD_MISMATCH` | ভুল payment method |
| `AMOUNT_MISMATCH` | Amount match হয়নি |
| `STORE_MISMATCH` | Store slug ভুল |
| `TRANSACTION_USED` | TRX আগে ব্যবহৃত |

---

## 📜 License

MIT License. See [LICENSE](LICENSE) for details.
