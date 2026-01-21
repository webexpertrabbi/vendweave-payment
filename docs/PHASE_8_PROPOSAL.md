# 🏅 Phase-8 — VendWeave Certified Integration Badge System

**Proposal Date:** January 22, 2026  
**Status:** ✅ IMPLEMENTED  
**Depends On:** Phase 1-7 (all complete)  
**Version:** v1.9.0

---

## 📋 Executive Summary

Introduce an official certification badge system to validate, govern, and publicly verify VendWeave SDK integrations across platforms. This enables trust verification for merchants, customers, and partners.

---

## 🎯 Objectives

1. **Trust Verification** — Allow merchants to prove their integration is certified
2. **Feature Compliance** — Ensure integrations meet security/governance standards
3. **Ecosystem Governance** — Enable VendWeave to revoke/downgrade non-compliant integrations
4. **Marketing Asset** — Provide official badges for websites/apps

---

## 🏷️ Badge Types

| Badge Code | Name | Requirement | Color |
|:-----------|:-----|:------------|:------|
| `VW-CERT-BASE` | Base Certified | SDK integrated & verified | 🟢 Green |
| `VW-CERT-REF` | Reference Certified | Reference strict mode enabled | 🔵 Blue |
| `VW-CERT-GOV` | Governance Certified | Reference governance engine active | 🟣 Purple |
| `VW-CERT-FIN` | Financial Certified | Financial reconciliation engine enabled | 🟠 Orange |
| `VW-CERT-CUR` | Currency Certified | Multi-currency normalization enabled | 🔴 Gold |

### Badge Hierarchy

```
VW-CERT-CUR (Gold)      ← Highest: All features + multi-currency
    ↑
VW-CERT-FIN (Orange)    ← Financial reconciliation enabled
    ↑
VW-CERT-GOV (Purple)    ← Reference governance engine
    ↑
VW-CERT-REF (Blue)      ← Strict reference mode
    ↑
VW-CERT-BASE (Green)    ← Base SDK integration
```

---

## 📊 Badge Data Model

### Table: `vendweave_certifications`

```php
Schema::create('vendweave_certifications', function (Blueprint $table) {
    $table->id();
    $table->string('badge_code', 20)->index();
    $table->string('project_name');
    $table->string('domain_or_app_id')->unique();
    $table->string('sdk_version', 20);
    $table->string('store_slug')->nullable();
    $table->string('verification_hash', 64)->unique();
    $table->json('features_detected')->nullable();
    $table->enum('status', ['active', 'revoked', 'expired', 'pending'])->default('pending');
    $table->timestamp('issued_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamp('revoked_at')->nullable();
    $table->string('revoke_reason')->nullable();
    $table->timestamps();

    $table->index(['status', 'badge_code']);
    $table->index(['domain_or_app_id', 'status']);
});
```

### Badge Status Lifecycle

```
PENDING → ACTIVE → EXPIRED
              ↓
          REVOKED
```

---

## ✅ Badge Issuance Requirements

### Qualification Criteria

| Badge | SDK Version | Config Flags | Auto-Detect Services | Test Suite |
|:------|:------------|:-------------|:---------------------|:-----------|
| `VW-CERT-BASE` | ≥1.0.0 | `api_key`, `store_slug` set | `TransactionVerifier` | Basic poll |
| `VW-CERT-REF` | ≥1.2.0 | `reference_strict_mode=true` | — | Reference tests |
| `VW-CERT-GOV` | ≥1.6.0 | `reference_governance.enabled=true` | `ReferenceGovernor` | Governance tests |
| `VW-CERT-FIN` | ≥1.7.0 | `financial_reconciliation.enabled=true` | `FinancialRecordManager` | Financial tests |
| `VW-CERT-CUR` | ≥1.8.0 | `base_currency` set | `CurrencyNormalizer` | Currency tests |

### Issuance Process

```
1. Developer registers domain/app_id on VendWeave Dashboard
2. SDK auto-detects enabled features
3. VendWeave::requestCertification() sends feature snapshot
4. VendWeave Authority validates & issues badge
5. Badge hash generated and stored
6. Developer embeds badge on site
```

---

## 🔌 SDK Integration

### New Service: `CertificationManager`

```php
<?php

namespace VendWeave\Gateway\Services;

class CertificationManager
{
    public const BADGE_BASE = 'VW-CERT-BASE';
    public const BADGE_REF = 'VW-CERT-REF';
    public const BADGE_GOV = 'VW-CERT-GOV';
    public const BADGE_FIN = 'VW-CERT-FIN';
    public const BADGE_CUR = 'VW-CERT-CUR';

    /**
     * Get current certification status from cache/API.
     */
    public static function status(): ?array
    {
        // Check local cache first
        // Then verify with VendWeave API
    }

    /**
     * Detect which badge the integration qualifies for.
     */
    public static function detectQualifiedBadge(): string
    {
        if (self::qualifiesForCurrency()) return self::BADGE_CUR;
        if (self::qualifiesForFinancial()) return self::BADGE_FIN;
        if (self::qualifiesForGovernance()) return self::BADGE_GOV;
        if (self::qualifiesForReference()) return self::BADGE_REF;
        return self::BADGE_BASE;
    }

    /**
     * Request certification from VendWeave Authority.
     */
    public static function requestCertification(string $domain, string $projectName): ?array
    {
        // POST to VendWeave certification API
    }

    /**
     * Verify a badge hash is valid.
     */
    public static function verifyBadge(string $hash): ?array
    {
        // GET /certify/verify/{hash}
    }

    /**
     * Get badge embed HTML.
     */
    public static function getBadgeHtml(): string
    {
        $status = self::status();
        if (!$status || $status['status'] !== 'active') {
            return '';
        }
        return self::generateBadgeEmbed($status);
    }

    // ... qualification check methods
}
```

### Facade Method

```php
// In VendWeave Facade
VendWeave::certificationStatus();
VendWeave::requestCertification($domain, $projectName);
VendWeave::verifyBadge($hash);
VendWeave::getBadgeHtml();
```

### Helper Method

```php
// In VendWeaveHelper
VendWeaveHelper::getCertificationBadge();
```

---

## 🌐 Verification API Endpoint

### Request

```http
GET https://vendweave.com/api/certify/verify/{verification_hash}
```

### Response (Valid Badge)

```json
{
    "valid": true,
    "project_name": "MyStore",
    "domain": "mystore.com",
    "badge_code": "VW-CERT-FIN",
    "badge_name": "Financial Certified",
    "status": "active",
    "issued_at": "2026-01-22T10:00:00Z",
    "expires_at": "2027-01-22T10:00:00Z",
    "sdk_version": "1.8.0",
    "features": [
        "reference_governance",
        "financial_reconciliation",
        "multi_currency"
    ]
}
```

### Response (Invalid/Revoked)

```json
{
    "valid": false,
    "status": "revoked",
    "revoked_at": "2026-06-15T08:30:00Z",
    "reason": "SDK version outdated"
}
```

---

## 🎨 Badge Assets

### CDN Delivery

```
https://cdn.vendweave.com/badges/VW-CERT-BASE.svg
https://cdn.vendweave.com/badges/VW-CERT-BASE.png
https://cdn.vendweave.com/badges/VW-CERT-REF.svg
...
```

### Badge Sizes

| Size | Dimensions | Use Case |
|:-----|:-----------|:---------|
| Small | 80x20 | Inline text |
| Medium | 150x40 | Footer |
| Large | 200x60 | Payment page |

### Embed Code

```html
<!-- Static Badge -->
<a href="https://vendweave.com/verify/abc123hash">
    <img src="https://cdn.vendweave.com/badges/VW-CERT-FIN.svg" 
         alt="VendWeave Financial Certified" />
</a>

<!-- Dynamic Badge (with verification) -->
<script src="https://cdn.vendweave.com/badge.js" 
        data-hash="abc123hash"></script>
```

---

## 🔒 Security

### Verification Hash Generation

```php
$hash = hash_hmac('sha256', implode('|', [
    $domain,
    $badgeCode,
    $sdkVersion,
    $issuedAt->timestamp,
    $expiresAt->timestamp,
]), config('vendweave.certification_secret'));
```

### Anti-Tamper Measures

1. **Signed Hash** — HMAC-SHA256 with secret key
2. **Expiry** — Badges expire after 1 year, require renewal
3. **Domain Lock** — Badge only valid for registered domain
4. **Real-time Verification** — JavaScript badge checks API on render

### Revocation Triggers

| Trigger | Action |
|:--------|:-------|
| SDK version < minimum | Warning → Revoke after 30 days |
| Domain change | Immediate revocation |
| Security incident | Immediate revocation |
| Payment fraud detected | Immediate revocation |
| Integration tampering | Immediate revocation |

---

## 🛠️ Config Additions

```php
// config/vendweave.php

/*
|--------------------------------------------------------------------------
| Certification Badge System (Phase-8)
|--------------------------------------------------------------------------
*/

'certification' => [
    'enabled' => env('VENDWEAVE_CERTIFICATION_ENABLED', true),
    'domain' => env('VENDWEAVE_CERT_DOMAIN'),
    'project_name' => env('VENDWEAVE_CERT_PROJECT'),
    'cache_ttl' => 3600, // Cache status for 1 hour
    'auto_renew' => true,
],
```

---

## 📦 Artisan Commands

```bash
# Check current certification status
php artisan vendweave:cert-status

# Request new certification
php artisan vendweave:cert-request --domain=mystore.com --project="My Store"

# Verify a badge hash
php artisan vendweave:cert-verify abc123hash

# Renew expiring certification
php artisan vendweave:cert-renew
```

---

## 📋 Implementation Checklist

### SDK Side (v1.9.0)

- [ ] `CertificationManager` service
- [ ] Facade methods
- [ ] Helper methods
- [ ] Config additions
- [ ] Artisan commands
- [ ] Badge embed generator
- [ ] Cache layer for status

### VendWeave Authority Side

- [ ] Certification API endpoints
- [ ] Dashboard certification UI
- [ ] Badge asset CDN
- [ ] Verification widget JS
- [ ] Revocation system
- [ ] Renewal notifications

### Documentation

- [ ] Certification guide
- [ ] Badge embedding guide
- [ ] API documentation
- [ ] Troubleshooting guide

---

## 📈 Success Metrics

| Metric | Target |
|:-------|:-------|
| Certified integrations | 100+ in first quarter |
| Badge verification rate | >95% valid |
| Renewal rate | >80% |
| Revocation rate | <5% |

---

## 🗓️ Timeline

| Milestone | Target Date |
|:----------|:------------|
| Proposal Approved | Jan 2026 |
| SDK Implementation | Feb 2026 |
| Authority API Ready | Feb 2026 |
| Beta Testing | Mar 2026 |
| Public Launch | Mar 2026 |
| v1.9.0 Release | Apr 2026 |

---

## 📝 Open Questions

1. **Badge Renewal** — Auto-renew or require manual action?
2. **Downgrade Path** — If features disabled, auto-downgrade badge?
3. **Multi-Domain** — Allow one project to have multiple domains?
4. **Free Tier** — Base badge free, premium badges paid?
5. **Audit Logs** — Expose certification history to developers?

---

## ✅ Approval

| Role | Name | Status |
|:-----|:-----|:------:|
| SDK Lead | — | ⏳ Pending |
| Product | — | ⏳ Pending |
| Security | — | ⏳ Pending |
| Authority | — | ⏳ Pending |

---

*Proposal authored: January 22, 2026*  
*Phase-8 of VendWeave SDK Evolution*
