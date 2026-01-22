# VendWeave Laravel SDK — Release Notes v1.9.1

**Release Date:** 2026-01-22  
**Status:** Protocol-Aligned Patch  

---

## ✅ Summary

This patch finalizes POS contract alignment with the WordPress reference implementation. The SDK now follows the exact lifecycle sequence required by POS, including reference reservation, correct payload field names, and poll → verify escalation.

---

## ✅ Highlights

- **POS Contract Compliance**
  - `payment_reference`, `expected_amount`, `wc_order_id` now match POS contract
  - Response normalization supports `transaction_status`, `pay_via`, `transaction_id`

- **Lifecycle Alignment**
  - Poll now escalates to verify when `trx_id` is present
  - Verification flow matches WordPress exactly

- **Package Safety**
  - No migration dependency
  - No POS dependency required for SDK correctness
  - Graceful failure when POS is unavailable

---

## ✅ Verification Status

**SDK is officially marked Protocol Aligned.**

See report: [docs/VERIFICATION_REPORT_PROTOCOL_ALIGNMENT.md](VERIFICATION_REPORT_PROTOCOL_ALIGNMENT.md)
