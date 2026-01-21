# VendWeave Certification Authority API Contract

**Version:** 1.0.0  
**Phase:** 8  
**Status:** Specification  

---

## Overview

This document defines the API contract between the VendWeave SDK and the VendWeave Certification Authority. The SDK implements the client side; the Authority implements the server side.

---

## Authentication

All requests must include authentication headers:

```http
X-VendWeave-Key: {api_key}
X-VendWeave-Store: {store_slug}
Content-Type: application/json
Accept: application/json
```

---

## Endpoints

### 1. Request Certification

Request a new certification badge for an integration.

**Endpoint:** `POST /api/certify/request`

**Request Body:**

```json
{
    "domain": "mystore.com",
    "project_name": "My Store",
    "store_slug": "my-store",
    "snapshot": {
        "sdk_version": "1.9.0",
        "qualified_badge": "VW-CERT-FIN",
        "features": {
            "base": true,
            "reference_strict": true,
            "governance": true,
            "financial": true,
            "currency": false
        },
        "services": {
            "transaction_verifier": true,
            "reference_governor": true,
            "financial_record_manager": true,
            "currency_normalizer": true
        },
        "config": {
            "api_key_set": true,
            "store_slug_set": true,
            "reference_strict_mode": true,
            "reference_governance_enabled": true,
            "financial_reconciliation_enabled": true,
            "base_currency_set": true
        },
        "timestamp": "2026-01-22T10:00:00Z"
    }
}
```

**Response (Success - 201):**

```json
{
    "status": "active",
    "badge_code": "VW-CERT-FIN",
    "badge_name": "Financial Certified",
    "project_name": "My Store",
    "domain": "mystore.com",
    "sdk_version": "1.9.0",
    "verification_hash": "a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456",
    "issued_at": "2026-01-22T10:00:00Z",
    "expires_at": "2027-01-22T10:00:00Z",
    "features": [
        "reference_governance",
        "financial_reconciliation"
    ],
    "badge_url": "https://cdn.vendweave.com/badges/VW-CERT-FIN.svg",
    "verify_url": "https://vendweave.com/verify/a1b2c3d4..."
}
```

**Response (Pending - 202):**

```json
{
    "status": "pending",
    "message": "Certification request is being reviewed",
    "request_id": "req_abc123"
}
```

**Response (Error - 400):**

```json
{
    "error": true,
    "error_code": "INVALID_DOMAIN",
    "message": "Domain is not registered in VendWeave Dashboard"
}
```

**Response (Error - 401):**

```json
{
    "error": true,
    "error_code": "UNAUTHORIZED",
    "message": "Invalid API credentials"
}
```

---

### 2. Get Certification Status

Check current certification status for a domain.

**Endpoint:** `GET /api/certify/status`

**Query Parameters:**

| Parameter | Type | Required | Description |
|:----------|:-----|:---------|:------------|
| `domain` | string | Yes | Domain or App ID |

**Request:**

```http
GET /api/certify/status?domain=mystore.com
```

**Response (Active - 200):**

```json
{
    "status": "active",
    "badge_code": "VW-CERT-FIN",
    "badge_name": "Financial Certified",
    "project_name": "My Store",
    "domain": "mystore.com",
    "sdk_version": "1.8.0",
    "verification_hash": "a1b2c3d4...",
    "issued_at": "2026-01-22T10:00:00Z",
    "expires_at": "2027-01-22T10:00:00Z",
    "features": ["reference_governance", "financial_reconciliation"]
}
```

**Response (Revoked - 200):**

```json
{
    "status": "revoked",
    "badge_code": null,
    "revoked_at": "2026-06-15T08:30:00Z",
    "revoke_reason": "SDK version outdated",
    "message": "Certification has been revoked"
}
```

**Response (Not Found - 404):**

```json
{
    "error": true,
    "error_code": "NOT_FOUND",
    "message": "No certification found for this domain"
}
```

---

### 3. Verify Badge Hash

Public endpoint to verify a badge hash. No authentication required.

**Endpoint:** `GET /api/certify/verify/{hash}`

**Request:**

```http
GET /api/certify/verify/a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456
```

**Response (Valid - 200):**

```json
{
    "valid": true,
    "project_name": "My Store",
    "domain": "mystore.com",
    "badge_code": "VW-CERT-FIN",
    "badge_name": "Financial Certified",
    "status": "active",
    "issued_at": "2026-01-22T10:00:00Z",
    "expires_at": "2027-01-22T10:00:00Z",
    "sdk_version": "1.8.0",
    "features": [
        "reference_governance",
        "financial_reconciliation"
    ]
}
```

**Response (Revoked - 200):**

```json
{
    "valid": false,
    "status": "revoked",
    "revoked_at": "2026-06-15T08:30:00Z",
    "reason": "SDK version outdated"
}
```

**Response (Expired - 200):**

```json
{
    "valid": false,
    "status": "expired",
    "expired_at": "2027-01-22T10:00:00Z",
    "message": "Certification has expired"
}
```

**Response (Not Found - 404):**

```json
{
    "valid": false,
    "status": "invalid",
    "message": "Badge hash not found"
}
```

---

### 4. Revoke Certification

Revoke a certification (Authority-initiated or store owner request).

**Endpoint:** `POST /api/certify/revoke`

**Request Body:**

```json
{
    "verification_hash": "a1b2c3d4...",
    "reason": "Security incident detected"
}
```

**Response (Success - 200):**

```json
{
    "status": "revoked",
    "revoked_at": "2026-06-15T08:30:00Z",
    "reason": "Security incident detected",
    "message": "Certification has been revoked"
}
```

---

### 5. Renew Certification

Renew an existing certification before expiry.

**Endpoint:** `POST /api/certify/renew`

**Request Body:**

```json
{
    "verification_hash": "a1b2c3d4...",
    "snapshot": {
        "sdk_version": "1.9.0",
        "qualified_badge": "VW-CERT-CUR",
        "features": {
            "base": true,
            "reference_strict": true,
            "governance": true,
            "financial": true,
            "currency": true
        },
        "services": {
            "transaction_verifier": true,
            "reference_governor": true,
            "financial_record_manager": true,
            "currency_normalizer": true
        },
        "config": {
            "api_key_set": true,
            "store_slug_set": true,
            "reference_strict_mode": true,
            "reference_governance_enabled": true,
            "financial_reconciliation_enabled": true,
            "base_currency_set": true
        },
        "timestamp": "2026-01-22T10:00:00Z"
    }
}
```

**Response (Renewed - 200):**

```json
{
    "status": "active",
    "badge_code": "VW-CERT-CUR",
    "badge_name": "Currency Certified",
    "previous_badge": "VW-CERT-FIN",
    "project_name": "My Store",
    "domain": "mystore.com",
    "sdk_version": "1.9.0",
    "verification_hash": "new_hash_abc123...",
    "issued_at": "2027-01-22T10:00:00Z",
    "expires_at": "2028-01-22T10:00:00Z",
    "upgraded": true,
    "features": [
        "reference_governance",
        "financial_reconciliation",
        "multi_currency"
    ]
}
```

**Response (Downgraded - 200):**

```json
{
    "status": "active",
    "badge_code": "VW-CERT-GOV",
    "badge_name": "Governance Certified",
    "previous_badge": "VW-CERT-FIN",
    "downgraded": true,
    "downgrade_reason": "Financial reconciliation table not detected",
    "verification_hash": "new_hash_xyz789...",
    "issued_at": "2027-01-22T10:00:00Z",
    "expires_at": "2028-01-22T10:00:00Z"
}
```

**Response (Cannot Renew - 400):**

```json
{
    "error": true,
    "error_code": "CANNOT_RENEW",
    "message": "Certification has been revoked and cannot be renewed"
}
```

---

## Error Codes

| Code | HTTP Status | Description |
|:-----|:------------|:------------|
| `UNAUTHORIZED` | 401 | Invalid API credentials |
| `INVALID_DOMAIN` | 400 | Domain not registered |
| `NOT_FOUND` | 404 | Certification not found |
| `ALREADY_CERTIFIED` | 409 | Domain already has active certification |
| `CANNOT_RENEW` | 400 | Certification revoked, cannot renew |
| `SDK_OUTDATED` | 400 | SDK version below minimum |
| `RATE_LIMITED` | 429 | Too many requests |
| `SERVER_ERROR` | 500 | Internal server error |

---

## Webhook Events (Optional)

The Authority may send webhook notifications for certification events.

### Event: `certification.revoked`

```json
{
    "event": "certification.revoked",
    "timestamp": "2026-06-15T08:30:00Z",
    "data": {
        "domain": "mystore.com",
        "verification_hash": "a1b2c3d4...",
        "reason": "SDK version outdated"
    }
}
```

### Event: `certification.expiring`

Sent 30 days before expiry.

```json
{
    "event": "certification.expiring",
    "timestamp": "2026-12-22T10:00:00Z",
    "data": {
        "domain": "mystore.com",
        "verification_hash": "a1b2c3d4...",
        "expires_at": "2027-01-22T10:00:00Z",
        "days_remaining": 30
    }
}
```

---

## Security Requirements

### Hash Generation (Server-Side)

```php
$hash = hash_hmac('sha256', implode('|', [
    $domain,
    $badgeCode,
    $sdkVersion,
    $issuedAt->timestamp,
    $expiresAt->timestamp,
    $nonce,
]), $certificationSecret);
```

### Domain Validation

- Domain must be registered in VendWeave Dashboard
- Domain ownership verified via DNS TXT record or meta tag
- Badge hash is domain-locked

### Rate Limiting

| Endpoint | Limit |
|:---------|:------|
| `/certify/request` | 5 per hour |
| `/certify/status` | 60 per minute |
| `/certify/verify/{hash}` | 100 per minute |
| `/certify/renew` | 5 per day |

---

## SDK Implementation Notes

1. **Graceful Degradation**: All API calls must handle failures silently
2. **Caching**: Status should be cached locally (default: 1 hour)
3. **Revocation Override**: Revocation status must override cache
4. **Auto-Renewal**: SDK may auto-renew when 30 days remain (if enabled)
5. **No Hard Dependency**: Certification is optional; SDK works without it

---

*Contract Version: 1.0.0*  
*Last Updated: January 22, 2026*
