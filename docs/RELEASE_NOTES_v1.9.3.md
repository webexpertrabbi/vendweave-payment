# Release Notes — VendWeave Laravel SDK v1.9.3

**Release Date:** January 23, 2026  
**Type:** Minor — Laravel 12 Full Compatibility

---

## Summary

Official Laravel 12 support with verified Composer install compatibility. Zero dependency conflicts, no GitHub token required.

---

## Changes

### Composer Dependencies

```json
"require": {
    "php": "^8.1",
    "illuminate/support": "^10.0|^11.0|^12.0",
    "illuminate/http": "^10.0|^11.0|^12.0",
    "illuminate/contracts": "^10.0|^11.0|^12.0",
    "guzzlehttp/guzzle": "^7.0"
}
```

### Removed

- `require-dev` section (no test artifacts in production package)
- `autoload-dev` section
- `tests/` folder
- `phpunit.xml`

---

## Compatibility Matrix

| Laravel | PHP | Status |
|---------|-----|--------|
| 10.x | 8.1+ | ✅ Supported |
| 11.x | 8.2+ | ✅ Supported |
| 12.x | 8.2+ | ✅ Supported |

---

## Installation

### Via Packagist

```bash
composer require vendweave/payment
```

### Via Path Repository

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../vendweave-payment"
        }
    ],
    "require": {
        "vendweave/payment": "*"
    }
}
```

```bash
composer require vendweave/payment:*
```

---

## Verification Checklist

- [x] `illuminate/support` ^12.0 compatible
- [x] `illuminate/http` ^12.0 compatible
- [x] `illuminate/contracts` ^12.0 compatible
- [x] ServiceProvider uses modern Laravel APIs
- [x] Events use constructor property promotion
- [x] Views use proper Blade syntax
- [x] Logging uses `Log::channel()->info()`
- [x] No deprecated helpers or facades
- [x] No test artifacts in package

---

## Certification

VendWeave SDK officially claims:

✅ **Laravel 10 Supported**  
✅ **Laravel 11 Supported**  
✅ **Laravel 12 Supported**

---

## Upgrade

```bash
composer update vendweave/payment
```

No configuration changes required.
