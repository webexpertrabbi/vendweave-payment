# Release Notes — VendWeave Laravel SDK v1.9.2

**Release Date:** January 23, 2026  
**Type:** Patch — POS Status Normalization

---

## Summary

Fixed SDK-level POS status normalization to prevent "unknown transaction status" errors and ensure graceful fallback behavior.

---

## Problem

Laravel users were receiving:

```
Verification Failed — Unknown transaction status: unknown
```

This occurred because POS APIs return inconsistent status field names and values, and the SDK treated unrecognized values as fatal errors.

---

## Solution

### 1. Expanded Status Field Detection

The SDK now auto-detects status from multiple POS response fields:

```php
'status' => ['status', 'transaction_status', 'payment_status', 'txn_status', 'state']
```

### 2. Canonical Status Mapping

All POS status variants are normalized to 5 canonical statuses:

| Canonical | POS Variants |
|-----------|--------------|
| `confirmed` | confirmed, success, paid, completed, approved, verified, matched |
| `pending` | pending, processing, waiting, initiated, in_progress |
| `expired` | expired, timeout, timed_out |
| `used` | used, replayed, duplicate, already_used |
| `failed` | failed, error, rejected, declined, cancelled |

### 3. Graceful Fallback

- Empty/unknown status → `pending`
- Unrecognized status → `pending` (with warning log)
- SDK never throws "unknown status" to clients

---

## Files Changed

| File | Change |
|------|--------|
| [VendWeaveApiClient.php](../src/Services/VendWeaveApiClient.php) | Expanded `normalizeStatusValue()` with full mapping |
| [TransactionVerifier.php](../src/Services/TransactionVerifier.php) | Removed fatal error on unknown status |
| [config/vendweave.php](../config/vendweave.php) | Added status field fallbacks |

---

## Upgrade

```bash
composer update vendweave/payment
```

No configuration changes required. No client code changes needed.

---

## Compatibility

- PHP 8.1+
- Laravel 10.x, 11.x, 12.x
- Backward compatible with v1.9.1
