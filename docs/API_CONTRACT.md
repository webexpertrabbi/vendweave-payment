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

### Reserve Reference

```
POST /api/v1/woocommerce/reserve-reference
```

### Poll Transaction

```
POST /api/v1/woocommerce/poll-transaction
```

### Verify Transaction (Confirmation)

```
POST /api/v1/woocommerce/verify-transaction
```

### SMS Receiver

```
POST /api/stores/{store_slug}/sms-receiver
```

---

## POS Payload Schema

### Reserve Reference (required)

| Field              | Type    | Required | Description                  |
| ------------------ | ------- | -------- | ---------------------------- |
| payment_reference  | string  | Yes      | Reference code (e.g., VW5067) |
| payment_method     | string  | Yes      | bkash/nagad/rocket/upay      |
| expected_amount    | decimal | Yes      | Payment amount                |
| wc_order_id        | string  | Yes      | Order ID                      |

### Poll Transaction

| Field              | Type    | Required | Description             |
| ------------------ | ------- | -------- | ----------------------- |
| payment_reference  | string  | Yes      | Reference code          |
| payment_method     | string  | Yes      | bkash/nagad/rocket/upay |
| expected_amount    | decimal | Yes      | Payment amount          |
| wc_order_id        | string  | Yes      | Order ID                |

### Verify Transaction (Confirmation)

| Field              | Type    | Required | Description             |
| ------------------ | ------- | -------- | ----------------------- |
| trx_id             | string  | Yes      | Transaction ID          |
| payment_reference  | string  | Yes      | Reference code          |
| payment_method     | string  | Yes      | bkash/nagad/rocket/upay |
| expected_amount    | decimal | Yes      | Payment amount          |
| wc_order_id        | string  | Yes      | Order ID                |

---

## Response Example

```json
{
  "status": "confirmed",
  "trx_id": "BKA123XYZ",
  "payment_reference": "VW5067",
  "payment_method": "bkash",
  "expected_amount": 960.0,
  "wc_order_id": "45"
}
```

---

## Status Lifecycle

| Status      | Description                                |
| ----------- | ------------------------------------------ |
| `pending`   | Transaction awaiting confirmation          |
| `confirmed` | Transaction verified successfully          |
| `failed`    | Transaction failed                         |
| `used`      | Transaction already used for another order |
| `expired`   | Transaction has expired                    |

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
