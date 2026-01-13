# VendWeave Laravel Payment Gateway

VendWeave একটি production-grade Laravel payment gateway package, যা VendWeave POS infrastructure ব্যবহার করে নিরাপদভাবে payment verification সম্পন্ন করে।

এই প্যাকেজটি bKash, Nagad, Rocket এবং Upay সমর্থন করে।

---

## 🚀 Features

- 🔐 Secure API Authentication (API Key + Secret)
- 🏪 Store Scoped Transaction Verification
- 💰 Exact Amount Matching (Zero tolerance)
- ⚡ Real-time Polling Based Verification
- 🎨 Fintech-grade Verification UI
- 🚦 Built-in Rate Limiting
- 🧩 Laravel Native Integration
- 🧾 POS as Single Source of Truth

---

## 💳 Supported Payment Methods

- bKash
- Nagad
- Rocket
- Upay

---

## 🏗 Architecture

```
Laravel App
→ VendWeave Gateway Package
→ VendWeave POS API
```

Laravel কখনো নিজে payment success সিদ্ধান্ত নেয় না।  
VendWeave POS সবসময় authority।

---

## 📦 Installation

```bash
composer require vendweave/gateway
php artisan vendor:publish --tag=vendweave-config
```

---

## ⚙️ Environment Configuration

```env
VENDWEAVE_API_KEY=your_api_key
VENDWEAVE_API_SECRET=your_api_secret
VENDWEAVE_STORE_SLUG=your_store_slug
VENDWEAVE_API_ENDPOINT=https://vendweave.com/api
```

---

## 🔁 Payment Flow

1. User checkout করে
2. VendWeave payment method select করে
3. Verify page এ redirect হয়
4. User mobile app থেকে payment করে
5. Package POS API poll করে
6. POS confirm দিলে order paid হয়

---

## 📚 Documentation

- [Laravel Integration Guide](docs/INTEGRATION_GUIDE.md)
- [Field Mapping Guide](docs/FIELD_MAPPING.md) ← _যদি field নাম আলাদা হয়_
- [POS API Contract](docs/API_CONTRACT.md)
- [Website Product Copy](docs/WEBSITE_COPY.md)

---

## ✅ Production Status

Version: **v1.0.0**  
Status: **Production Ready**

---

## 📜 License

MIT License

---

**VendWeave Team**
