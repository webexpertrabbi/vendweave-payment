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

## Endpoints (Laravel SDK Namespace v1.10.0+)

### Reserve Reference

```
POST /api/sdk/laravel/reserve-reference
```

### Poll Transaction

```
POST /api/sdk/laravel/poll
```

### Verify Transaction

```
POST /api/sdk/laravel/verify
```

### Confirm Transaction

```
POST /api/sdk/laravel/confirm
```

---

## SDK Verification Flow

```
reserveReference() → poll() → verify() → confirm() → status=used → SUCCESS
```

---

## POS Payload Schema

### Reserve Reference (required)

| Field              | Type    | Required | Description                  |
| ------------------ | ------- | -------- | ---------------------------- |
| payment_reference  | string  | Yes      | Reference code (e.g., VW5067) |
| payment_method     | string  | Yes      | bkash/nagad/rocket/upay      |
| expected_amount    | decimal | Yes      | Payment amount                |
| order_id           | string  | Yes      | Order ID                      |

### Poll Transaction

| Field              | Type    | Required | Description             |
| ------------------ | ------- | -------- | ----------------------- |
| payment_reference  | string  | Yes      | Reference code          |
| payment_method     | string  | Yes      | bkash/nagad/rocket/upay |
| expected_amount    | decimal | Yes      | Payment amount          |
| order_id           | string  | Yes      | Order ID                |

### Verify Transaction

| Field              | Type    | Required | Description             |
| ------------------ | ------- | -------- | ----------------------- |
| trx_id             | string  | Yes      | Transaction ID          |
| payment_reference  | string  | Yes      | Reference code          |
| payment_method     | string  | Yes      | bkash/nagad/rocket/upay |
| expected_amount    | decimal | Yes      | Payment amount          |
| order_id           | string  | Yes      | Order ID                |

### Confirm Transaction

| Field              | Type    | Required | Description             |
| ------------------ | ------- | -------- | ----------------------- |
| trx_id             | string  | Yes      | Transaction ID          |
| payment_reference  | string  | Yes      | Reference code          |

---

## Response Example

```json
{
  "status": "used",
  "trx_id": "BKA123XYZ",
  "payment_reference": "VW5067",
  "payment_method": "bkash",
  "expected_amount": 960.0,
  "order_id": "45"
}
```

---

## Status Lifecycle (POS v2)

| Status      | Description                                    | Frontend Action |
| ----------- | ---------------------------------------------- | --------------- |
| `pending`   | Transaction awaiting payment                   | Keep polling    |
| `verified`  | Transaction found, awaiting confirm            | Keep polling    |
| `confirmed` | Transaction verified (SDK must call confirm)   | Keep polling    |
| `success`   | Same as confirmed (SDK must call confirm)      | Keep polling    |
| `used`      | Transaction consumed/locked ✅                 | **Redirect success** |
| `failed`    | Transaction failed                             | **Redirect failed** |
| `expired`   | Transaction has expired                        | **Redirect failed** |

---

## Redirect Rules

| POS Status    | Action                |
| ------------- | --------------------- |
| `pending`     | Stay on verify page   |
| `verified`    | Keep polling          |
| `confirmed`   | Keep polling ❌ NO redirect |
| `success`     | Keep polling ❌ NO redirect |
| `used`        | ✅ Redirect success   |
| `failed`      | ✅ Redirect failed    |
| `expired`     | ✅ Redirect failed    |

---

## Field Mapping (POS ↔ Common Names)

| POS Field            | Common Name         |
| -------------------- | ------------------- |
| payment_reference    | reference           |
| wc_order_id          | order_id            |
| expected_amount      | amount              |
| payment_method       | payment_method      |
| trx_id               | trx_id              |
| transaction_status   | status              |
| pay_via              | payment_method      |
| transaction_id       | trx_id              |
| payment_amount       | amount              |

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
