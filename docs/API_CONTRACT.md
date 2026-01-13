# VendWeave POS Payment Verification API

## Base URL

```
https://vendweave.com/api
```

---

## Authentication

All requests must include:

```
Authorization: Bearer {API_KEY}
X-Store-Secret: {API_SECRET}
```

---

## Endpoints

### Poll Transaction

Used for auto-polling from verification page.

```
POST /api/v1/woocommerce/poll-transaction
```

### Verify Transaction

Used for manual transaction verification with TRX ID.

```
POST /api/v1/woocommerce/verify-transaction
```

### SMS Receiver

Receives SMS forwarded from store device.

```
POST /api/stores/{store_slug}/sms-receiver
```

---

## Request Parameters

| Field          | Type    | Required | Description                 |
| -------------- | ------- | -------- | --------------------------- |
| store_slug     | string  | Yes      | Unique store identifier     |
| order_id       | string  | Yes      | Order ID from Laravel       |
| trx_id         | string  | Optional | Transaction ID (for verify) |
| payment_method | string  | Yes      | bkash/nagad/rocket/upay     |
| amount         | decimal | Yes      | Exact payment amount        |

---

## Response Example

```json
{
  "status": "confirmed",
  "trx_id": "BKA123XYZ",
  "amount": 960.0,
  "payment_method": "bkash",
  "store_slug": "my-store"
}
```

---

## Status Values

| Status      | Description                                |
| ----------- | ------------------------------------------ |
| `pending`   | Transaction awaiting confirmation          |
| `confirmed` | Transaction verified successfully          |
| `failed`    | Transaction failed                         |
| `used`      | Transaction already used for another order |
| `expired`   | Transaction has expired                    |

---

## Store Identity

> ⚠️ **Important**: Store identity uses `store_slug` (string), never numeric ID.

Example store slugs:

- `my-electronics-shop`
- `fashion-hub-bd`
- `grocery-mart`

---

## Validation Rules

- Amount অবশ্যই exact match করবে
- Store slug match না হলে reject
- Method mismatch হলে reject
- Used transaction পুনরায় ব্যবহার করা যাবে না
- Expired transaction invalid

---

## Error Codes

| Code                       | Description                         |
| -------------------------- | ----------------------------------- |
| `TRANSACTION_NOT_FOUND`    | Transaction ID not found in POS     |
| `AMOUNT_MISMATCH`          | Amount doesn't match exactly        |
| `METHOD_MISMATCH`          | Payment method doesn't match        |
| `STORE_MISMATCH`           | Store scope violation               |
| `TRANSACTION_ALREADY_USED` | Transaction linked to another order |
| `TRANSACTION_EXPIRED`      | Transaction is too old              |
| `INVALID_CREDENTIALS`      | Missing or invalid API credentials  |

---

## Authority Rule

> VendWeave POS হচ্ছে একমাত্র payment authority।  
> Laravel system শুধুমাত্র POS এর সিদ্ধান্ত গ্রহণ করবে।
