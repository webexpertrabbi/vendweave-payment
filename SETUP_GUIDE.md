# VendWeave Payment Package (Laravel SDK) — Complete Setup Guide

এই ডকুমেন্টটি একা পড়লেই ০‑লেভেল থেকে সব বুঝে নেবেন। এটি Laravel স্টোরে VendWeave POS‑এর payment verification UI যোগ করে।

## ১) কী কী লাগবে (Mandatory)
- VendWeave POS‑এ Store/Account
- Store Slug + API Key + API Secret
- যে ফোনে টাকা রিসিভ হবে, সেই ফোনে **SMS Reader App** ইনস্টল
- একটি Laravel অ্যাপ্লিকেশন

## ২) POS থেকে Store Info নিন
POS‑এ Store Settings → API Settings:
- Store Slug
- API Key
- API Secret

## ৩) প্যাকেজ ইনস্টল
আপনার Laravel প্রজেক্টে প্যাকেজ ইনস্টল করুন (composer অনুযায়ী)।

## ৪) কনফিগ সেট করুন
`.env` অথবা config এ দিন:
- `VENDWEAVE_STORE_SLUG`
- `VENDWEAVE_API_KEY`
- `VENDWEAVE_API_SECRET`

## ৫) Verify Page / Polling
প্যাকেজ verify page polling করে POS‑এ status check করে।
- Polling limits POS থেকে enforce হয়
- Mismatch হলে verify page warning দেখায়

## ৬) SMS Reader App বাধ্যতামূলক
1. যেই ফোনে টাকা আসে, সেই ফোনে SMS App ইনস্টল করুন
2. App‑এ একই Store Slug + API key/secret বসান
3. Monitoring চালু রাখুন

## ৭) পুরো ফ্লো (End‑to‑End)
1. কাস্টমার payment করে
2. SMS আসে → App POS‑এ পাঠায়
3. POS verify করে
4. Laravel verify page auto‑update হয়

## ৮) Troubleshooting
- API key mismatch → 401
- Store slug ভুল → 404
- App বন্ধ থাকলে auto verify হবে না

## ৯) সম্পর্কিত ডকুমেন্টেশন
- POS Guide: [../POS_SETUP_GUIDE.md](../POS_SETUP_GUIDE.md)
- SMS App Guide: [../vendweave-sms-reader-store-admin/SETUP_GUIDE.md](../vendweave-sms-reader-store-admin/SETUP_GUIDE.md)
