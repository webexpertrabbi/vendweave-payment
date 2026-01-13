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
        'bkash',
        'nagad',
        'rocket',
        'upay',
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
];
