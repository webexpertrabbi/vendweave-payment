<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VendWeave API Credentials
    |--------------------------------------------------------------------------
    |
    | Your VendWeave POS API credentials. These are required for the package
    | to communicate with the VendWeave POS system.
    |
    | ⚠️ IMPORTANT: Use the correct API credentials for your integration type:
    |
    | 🌍 Website/Laravel Integration:
    |    - Use "General API Credentials" or "Website API Keys" from dashboard
    |
    | 📱 Android SMS App:
    |    - Use "Manual Payment API Keys" from dashboard
    |
    | ❌ Using the wrong credential type will result in 401 Unauthorized errors.
    |
    */

    'api_key' => env('VENDWEAVE_API_KEY'),
    'api_secret' => env('VENDWEAVE_API_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | API Endpoint
    |--------------------------------------------------------------------------
    |
    | The VendWeave POS API endpoint.
    |
    | Production: https://vendweave.com/api
    |
    */

    'endpoint' => env('VENDWEAVE_API_ENDPOINT', 'https://vendweave.com/api'),

    /*
    |--------------------------------------------------------------------------
    | Store Configuration
    |--------------------------------------------------------------------------
    |
    | Store slug for store scope isolation. Every transaction verification
    | will include the store_slug in requests. Use your store's unique slug.
    |
    */

    'store_slug' => env('VENDWEAVE_STORE_SLUG'),

    /*
    |--------------------------------------------------------------------------
    | SSL Verification
    |--------------------------------------------------------------------------
    |
    | Enable/disable SSL certificate verification. Set to false ONLY for
    | local development if you encounter SSL certificate errors.
    |
    | ⚠️ WARNING: Never disable SSL verification in production!
    |
    */

    'verify_ssl' => env('VENDWEAVE_VERIFY_SSL', true),

    /*
    |--------------------------------------------------------------------------
    | Polling Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the transaction verification polling mechanism.
    | The verify page will poll the POS API at the specified interval.
    |
    */

    'polling' => [
        'interval_ms' => 2500,          // Poll every 2.5 seconds
        'max_attempts' => 120,          // Maximum 120 attempts (5 minutes)
        'timeout_seconds' => 300,       // Overall timeout in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting configuration for the poll endpoint to prevent abuse.
    |
    */

    'rate_limit' => [
        'max_attempts' => 60,           // Maximum requests per decay period
        'decay_minutes' => 1,           // Decay period in minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the route prefix and middleware for VendWeave routes.
    |
    */

    'routes' => [
        'prefix' => 'vendweave',
        'middleware' => ['web'],
        'api_middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Payment Methods
    |--------------------------------------------------------------------------
    |
    | List of supported mobile financial service payment methods.
    | These are the only methods that will be accepted for verification.
    |
    */

    'payment_methods' => [
        'bkash' => [
            'number' => env('VENDWEAVE_BKASH_NUMBER', '01XXXXXXXXX'),
            'type' => 'personal', // personal or merchant
            'instruction' => 'Send money to this Bkash Personal Number using Send Money option.',
        ],
        'nagad' => [
            'number' => env('VENDWEAVE_NAGAD_NUMBER', '01XXXXXXXXX'),
            'type' => 'personal',
            'instruction' => 'Send money to this Nagad Personal Number using Send Money option.',
        ],
        'rocket' => [
            'number' => env('VENDWEAVE_ROCKET_NUMBER', '01XXXXXXXXX'),
            'type' => 'personal',
            'instruction' => 'Send money to this Rocket Personal Number using Send Money option.',
        ],
        'upay' => [
            'number' => env('VENDWEAVE_UPAY_NUMBER', '01XXXXXXXXX'),
            'type' => 'personal',
            'instruction' => 'Send money to this Upay Personal Number using Send Money option.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Callbacks
    |--------------------------------------------------------------------------
    |
    | Configure callback URLs for payment success and failure.
    | Use route names or full URLs. Leave null to use default package routes.
    |
    */

    'callbacks' => [
        'success_route' => null,        // Route name or null for default
        'failed_route' => null,         // Route name or null for default
    ],

    /*
    |--------------------------------------------------------------------------
    | Reference Governance Engine
    |--------------------------------------------------------------------------
    |
    | Phase-5 feature flag and reference lifecycle settings.
    | If migration/table is missing, SDK falls back to Phase-4 behavior.
    |
    */

    'reference_governance' => [
        'enabled' => env('VENDWEAVE_REFERENCE_GOVERNANCE', true),
        'ttl_minutes' => env('VENDWEAVE_REFERENCE_TTL', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Financial Reconciliation Engine
    |--------------------------------------------------------------------------
    |
    | Phase-6 feature flag. If financial tables are missing, SDK falls back
    | to Phase-5 behavior without breaking the host app.
    |
    */

    'financial_reconciliation' => [
        'enabled' => env('VENDWEAVE_FINANCIAL_RECONCILIATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Currency Normalization (Phase-7)
    |--------------------------------------------------------------------------
    |
    | Base currency is the unified reporting currency.
    | Exchange rates can be fetched from API or static config.
    |
    */

    'base_currency' => env('VENDWEAVE_BASE_CURRENCY', 'USD'),

    'exchange_rate_source' => env('VENDWEAVE_EXCHANGE_SOURCE', 'static'), // api|static

    'static_rates' => [
        'BDT' => 0.0091,
        'EUR' => 1.08,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable logging for debugging and audit purposes.
    | All API interactions will be logged when enabled.
    |
    */

    'logging' => [
        'enabled' => env('VENDWEAVE_LOGGING', true),
        'channel' => env('VENDWEAVE_LOG_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Order Model Configuration
    |--------------------------------------------------------------------------
    |
    | Specify your Order model class. The package will use this model
    | for order lookups and updates.
    |
    */

    'order_model' => env('VENDWEAVE_ORDER_MODEL', 'App\\Models\\Order'),

    /*
    |--------------------------------------------------------------------------
    | Order Field Mapping
    |--------------------------------------------------------------------------
    |
    | Map your database column names to the package's expected fields.
    | This allows integration without changing your existing schema.
    |
    | Example: If your 'amount' column is named 'grand_total', set:
    | 'amount' => 'grand_total'
    |
    */

    'order_mapping' => [
        'id' => 'id',                           // Order ID column
        'amount' => 'total',                    // Amount/total column
        'payment_method' => 'payment_method',   // Payment method column
        'status' => 'status',                   // Order status column
        'trx_id' => 'trx_id',                   // Transaction ID column (nullable)
        'payment_reference' => 'payment_reference', // Payment reference column (nullable)
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Mapping
    |--------------------------------------------------------------------------
    |
    | Map VendWeave status values to your application's status values.
    | This is useful if you use integers, enums, or different strings.
    |
    | Example: If 'paid' in your app is represented as 1:
    | 'paid' => 1
    |
    */

    'status_mapping' => [
        'paid' => 'paid',           // When payment is confirmed
        'pending' => 'pending',     // When payment is pending
        'failed' => 'failed',       // When payment fails
    ],

    /*
    |--------------------------------------------------------------------------
    | Reference Strict Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, reference matching becomes mandatory. If reference is 
    | expected but doesn't match, transaction fails even if amount matches.
    |
    | Default: false (backward compatible - amount fallback allowed)
    |
    | Set VENDWEAVE_REFERENCE_STRICT=true in .env to enable strict mode.
    |
    */

    'reference_strict_mode' => env('VENDWEAVE_REFERENCE_STRICT', false),

    /*
    |--------------------------------------------------------------------------
    | Reference TTL (Time To Live)
    |--------------------------------------------------------------------------
    |
    | Maximum age of a reference in minutes. References older than this
    | are considered expired. POS API enforces expiry, SDK validates
    | the reference_status returned by POS.
    |
    | Default: 30 minutes
    |
    */

    'reference_ttl' => env('VENDWEAVE_REFERENCE_TTL', 30),

    /*
    |--------------------------------------------------------------------------
    | API Parameter Mapping (Vendor Contract Alignment)
    |--------------------------------------------------------------------------
    |
    | The VendWeave POS API expects specific parameter names.
    | This SDK automatically maps your internal field names to the API contract.
    |
    | POS API Contract:
    | - wc_order_id (not order_id)
    | - expected_amount (not amount)
    |
    | The SDK uses a two-layer system:
    | 1. Config-based mapping (this section)
    | 2. Auto-detection fallback (automatic)
    |
    | You don't need to change this unless the POS API contract changes.
    |
    */

    'api_param_mapping' => [
        'order_id' => 'wc_order_id',
        'amount' => 'expected_amount',
        'reference' => 'payment_reference',
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Field Auto-Detection
    |--------------------------------------------------------------------------
    |
    | The POS API may return different field names in responses.
    | The SDK automatically attempts to find these fields using fallback logic.
    |
    | This ensures compatibility even if the API response structure changes.
    |
    */

    'response_field_fallbacks' => [
        'order_id' => ['wc_order_id', 'order_id', 'order_no', 'invoice_id'],
        'amount' => ['expected_amount', 'amount', 'total', 'grand_total'],
        'store_slug' => ['store_slug', 'store_id', 'shop_slug'],
        'payment_method' => ['payment_method', 'method', 'gateway', 'payment_type', 'pay_via'],
        'reference' => ['payment_reference', 'reference', 'ref'],
        'trx_id' => ['trx_id', 'transaction_id', 'payment_id'],
        'status' => ['status', 'transaction_status', 'payment_status', 'txn_status', 'state'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Intelligent Amount Detection
    |--------------------------------------------------------------------------
    |
    | The SDK intelligently detects the actual payable amount (grand total)
    | using mathematical validation and priority-based field detection.
    |
    | Philosophy: SDK detects money logically, not linguistically.
    |
    | Strategy:
    | 1. Priority-based candidate detection
    | 2. Mathematical validation (subtotal - discount + shipping = payable)
    | 3. Conflict resolution when names are ambiguous
    |
    */

    'amount_detection' => [
        /*
        | Primary Fields (assumed to be final payable amount)
        | These are checked first and preferred over secondary fields.
        */
        'primary_fields' => [
            'expected_amount',
            'payable_amount',
            'final_amount',
            'grand_total',
            'total_amount',
            'order_total_amount',
        ],

        /*
        | Secondary Fields (might be subtotal, used as fallback)
        | Only used if no primary fields exist.
        */
        'secondary_fields' => [
            'total',
            'subtotal',
            'product_total',
        ],

        /*
        | Mathematical Validation
        | If these component fields exist, SDK will calculate and validate.
        */
        'enable_math_validation' => true,

        /*
        | Component Field Names
        | Used for mathematical validation: subtotal - discount + shipping + tax
        */
        'component_fields' => [
            'subtotal' => ['subtotal', 'sub_total', 'items_total'],
            'discount' => ['discount', 'discount_amount', 'coupon_discount'],
            'shipping' => ['shipping', 'shipping_cost', 'shipping_amount'],
            'tax' => ['tax', 'tax_amount', 'vat'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Certification Badge System (Phase-8)
    |--------------------------------------------------------------------------
    |
    | Enable official VendWeave certification badges to validate and publicly
    | verify your integration status. Badges are issued by VendWeave Authority
    | and can be embedded on your website/app.
    |
    | Badge Tiers:
    | - VW-CERT-BASE: SDK integrated & verified
    | - VW-CERT-REF:  Reference strict mode enabled
    | - VW-CERT-GOV:  Reference governance engine active
    | - VW-CERT-FIN:  Financial reconciliation enabled
    | - VW-CERT-CUR:  Multi-currency normalization enabled
    |
    */

    'certification' => [
        /*
        | Enable/disable the certification system.
        | When disabled, all certification methods return null safely.
        */
        'enabled' => env('VENDWEAVE_CERTIFICATION_ENABLED', false),

        /*
        | Domain or App ID for certification.
        | This must match the registered domain in VendWeave Dashboard.
        */
        'domain' => env('VENDWEAVE_CERT_DOMAIN'),

        /*
        | Human-readable project name for certification display.
        */
        'project_name' => env('VENDWEAVE_CERT_PROJECT'),

        /*
        | Cache TTL for certification status (in seconds).
        | Default: 1 hour. Set lower for more frequent verification.
        */
        'cache_ttl' => env('VENDWEAVE_CERT_CACHE_TTL', 3600),

        /*
        | Auto-renew certification before expiry.
        | When true, SDK will attempt renewal when 30 days remain.
        */
        'auto_renew' => env('VENDWEAVE_CERT_AUTO_RENEW', true),

        /*
        | VendWeave Authority API URL for certification requests.
        | Default uses production endpoint.
        */
        'authority_url' => env('VENDWEAVE_CERT_AUTHORITY_URL', 'https://vendweave.com/api'),

        /*
        | CDN URL for badge assets (SVG/PNG).
        */
        'cdn_url' => env('VENDWEAVE_CERT_CDN_URL', 'https://cdn.vendweave.com/badges'),

        /*
        | Verification page URL base.
        */
        'verify_url' => env('VENDWEAVE_CERT_VERIFY_URL', 'https://vendweave.com/verify'),
    ],
];

