# Changelog

All notable changes to the VendWeave Payment SDK will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangeled.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.7.0] - 2026-01-22

### 💼 Financial Reconciliation Engine

Adds accounting-grade reconciliation with financial records, settlements, and ledger exports. All migrations are optional and auto-detected; Phase-5 behavior remains when tables are missing.

### ✨ Added

- Financial tables (publishable):
  - `vendweave_financial_records`
  - `vendweave_settlements`
  - `vendweave_ledger_exports`
- Services:
  - `FinancialRecordManager`
  - `SettlementEngine`
  - `LedgerExporter`
- Artisan commands:
  - `vendweave:generate-settlement`
  - `vendweave:export-ledger`
  - `vendweave:reconcile`
- Config flag:
  - `financial_reconciliation.enabled`

### 🔄 Changed

- `TransactionVerifier` now conditionally creates financial records when tables exist.

---

## [1.6.0] - 2026-01-21

### 🧭 Reference Governance Engine

Adds lifecycle governance for payment references with replay prevention, expiry scheduling, and audit-ready logging. Migration is optional; the SDK safely falls back to Phase-4 behavior if the table is missing.

### ✨ Added

- `ReferenceGovernor` service with lifecycle methods:
  - `reserve`, `match`, `markReplay`, `cancel`, `expireOverdue`, `validate`, `stats`
- Reference governance migration (publishable):
  - `vendweave_references` table
- Artisan command:
  - `vendweave:expire-references`
- Config flags:
  - `reference_governance.enabled`
  - `reference_governance.ttl_minutes`

### 🔄 Changed

- `TransactionVerifier` now conditionally enforces reference governance when the table exists.

---

## [1.2.0] - 2026-01-14

### 🧠 Intelligent Amount Detection

**Philosophy**: SDK detects money logically, not linguistically.

This release adds intelligent payable amount detection that uses mathematical validation instead of blindly trusting field names.

### ✨ Added

- **AmountDetectionService** - New service for intelligent amount detection

  - Priority-based field detection (expected_amount, grand_total, etc.)
  - Mathematical validation: `subtotal - discount + shipping + tax = payable`
  - Conflict resolution when multiple amount fields exist
  - Graceful fallback with warning logging

- **Three-Tier Detection Strategy**:

  1. **Priority Detection**: Checks primary fields (expected_amount, payable_amount, final_amount, grand_total) before secondary fields (total, subtotal)
  2. **Mathematical Validation**: If component fields exist (subtotal, discount, shipping, tax), SDK calculates and validates against candidates
  3. **Conflict Resolution**: When ambiguous, selects highest primary field and logs warning

- **Configuration** (`config/vendweave.php`):
  ```php
  'amount_detection' => [
      'primary_fields' => ['expected_amount', 'payable_amount', 'final_amount', 'grand_total', ...],
      'secondary_fields' => ['total', 'subtotal', 'product_total'],
      'enable_math_validation' => true,
      'component_fields' => [...]
  ],
  ```

### 🔄 Changed

- **OrderAdapter.getAmount()** now uses intelligent detection instead of simple field lookup
- SDK automatically detects actual payable amount even when:
  - Field names are swapped (total vs grand_total)
  - Multiple amount fields exist
  - Coupon/discount/shipping exists
  - Field meanings are ambiguous

### 💡 Benefits

- **Prevents Payment Errors**: No more underpayment due to wrong field detection
- **Works with Any Schema**: Adapts to user's field naming conventions
- **Self-Validating**: Mathematical checks ensure correctness
- **Transparent**: Logs ambiguities for debugging

### Example Scenarios Handled

| Scenario                               | SDK Behavior                                                          |
| -------------------------------------- | --------------------------------------------------------------------- |
| Both `total` & `grand_total` exist     | Validates mathematically or chooses higher value                      |
| Only `subtotal` exists                 | Uses it as payable amount                                             |
| Has `subtotal`, `discount`, `shipping` | Calculates: `subtotal - discount + shipping` and finds matching field |
| Ambiguous field names                  | Selects best candidate + logs warning                                 |

---

## [1.1.0] - 2026-01-14

### 🎯 Major Improvements

This release transforms VendWeave from a basic gateway into a **production-grade SaaS SDK** with intelligent auto-adaptation capabilities.

### ✨ Added

- **Two-Layer Parameter Mapping System**

  - Config-based mapping (`api_param_mapping` in config)
  - Auto-detection fallback for backward compatibility
  - SDK automatically maps `order_id` → `wc_order_id` and `amount` → `expected_amount`
  - Users never need to change their code to match API contract

- **Intelligent Response Normalization**

  - Auto-converts List (indexed array) responses to Object (associative array)
  - Multi-field auto-detection (handles `wc_order_id`, `order_id`, `order_no`, `invoice_id`)
  - Automatic injection of missing `store_slug` from configuration
  - SDK adapts to API response structure changes automatically

- **Graceful Degradation**

  - `store_slug` validation now degrades gracefully when missing from API
  - Logs warnings instead of failing transactions
  - Enhanced production debugging with detailed logging

- **API Credential Type Documentation**

  - Clear warnings in config file about credential types
  - Explains difference between "General API Credentials" (website) vs "Manual Payment API Keys" (app)
  - Prevents common 401 Unauthorized errors

- **Response Field Fallbacks Configuration**
  - `response_field_fallbacks` config option
  - Handles multiple field name variations automatically
  - Future-proof against API contract changes

### 🔄 Changed

- **Package Name**: `vendweave/gateway` → `vendweave/payment`

  - Reflects universal payment SDK nature (not WooCommerce-specific)
  - Old package name deprecated

- **API Parameter Names** (Transparent to Users)
  - Now sends `wc_order_id` instead of `order_id` to POS API
  - Now sends `expected_amount` instead of `amount` to POS API
  - **No code changes required** - SDK handles mapping automatically
  - Eliminates 422 validation errors from POS API

### 🐛 Fixed

- Fixed 422 validation errors caused by incorrect parameter names
- Fixed parsing failures when API returns List instead of Object
- Fixed transaction verification failures when `store_slug` missing from API response
- Fixed "Unknown Status" errors by adding proper response normalization

### 📝 Documentation

- Added comprehensive CHANGELOG.md
- Updated config file with credential type warnings
- Enhanced inline code documentation
- Added migration notes for v1.0.0 users

---

## [1.0.0] - 2026-01-13

### Initial Release

- Basic VendWeave POS payment verification
- Support for bKash, Nagad, Rocket, Upay
- Real-time polling mechanism
- Store-scoped transaction verification
- Exact amount matching
- Event system (PaymentVerified, PaymentFailed)
- Order field mapping configuration
- Production-ready security features

---

## Migration Guide: 1.0.0 → 1.1.0

### For Existing Users

**Good News**: This is a **backward-compatible** update. No code changes required!

### Installation

```bash
composer require vendweave/payment
```

If you're upgrading from `vendweave/gateway`:

```bash
composer remove vendweave/gateway
composer require vendweave/payment
```

### What Changed Automatically

1. **Parameter Mapping**: SDK now sends correct parameters to POS API

   - Your code still uses `$order->id` and `$order->amount`
   - SDK automatically maps to `wc_order_id` and `expected_amount`

2. **Response Handling**: SDK now handles all response structure variations

   - No changes needed in your code
   - SDK normalizes everything automatically

3. **Store Validation**: More resilient to API response variations
   - Still secure, but won't fail if API doesn't return `store_slug`

### What You Should Do

1. **Update `.env` Credentials** (If you had 401 errors)

   - Make sure you're using **"General API Credentials"** from VendWeave dashboard
   - Not "Manual Payment API Keys" (those are for Android app only)

2. **Re-publish Config** (Optional, recommended)

   ```bash
   php artisan vendor:publish --tag=vendweave-config --force
   ```

   This gives you the new credential warnings and field fallback options.

3. **Clear Config Cache**
   ```bash
   php artisan config:clear
   ```

### Breaking Changes

**None** - All changes are backward compatible.

The new package name (`vendweave/payment`) is the only "breaking" change, but it's just a namespace update in Composer.

---

## Support

For issues and feature requests, please open an issue on GitHub.

**VendWeave Payment SDK — Adaptive, Intelligent, Production-Ready 🚀**
