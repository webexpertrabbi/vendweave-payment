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

> 🤖 **Note**: The SDK automatically maps your parameters to these required names.

| Field           | Type    | Required | Description                                |
| --------------- | ------- | -------- | ------------------------------------------ |
| store_slug      | string  | Yes      | Unique store identifier                    |
| wc_order_id     | string  | Yes      | Order ID (SDK auto-maps from order_id)     |
| expected_amount | decimal | Yes      | Payment amount (SDK auto-maps from amount) |
| payment_method  | string  | Yes      | bkash/nagad/rocket/upay                    |
| trx_id          | string  | Optional | Transaction ID (for verify endpoint)       |

### SDK Auto-Mapping

Your code can use standard field names:

- `order_id` → SDK maps to `wc_order_id`
- `amount` → SDK maps to `expected_amount`

No code changes required!

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

### API Response Handling

The POS API **should** return `store_slug` in responses, but the SDK gracefully handles missing store_slug:

- If present: Strict validation performed
- If missing: SDK injects from config + logs warning

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
