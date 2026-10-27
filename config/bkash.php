<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Account
    |--------------------------------------------------------------------------
    |
    | The account used when you call the facade directly, e.g.
    | Bkash::createPayment(...). Must be a key of the "accounts" array.
    |
    */

    'default' => env('BKASH_ACCOUNT', 'primary'),

    /*
    |--------------------------------------------------------------------------
    | API Base URLs (Tokenized Checkout v2)
    |--------------------------------------------------------------------------
    |
    | An account can override these with its own "base_url" key.
    |
    */

    'urls' => [
        'sandbox' => env('BKASH_SANDBOX_URL', 'https://tokenized.sandbox.bka.sh/v2'),
        'production' => env('BKASH_PRODUCTION_URL', 'https://tokenized.pay.bka.sh/v2'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | bKash recommends a 30 second timeout for every API.
    |
    */

    'timeout' => env('BKASH_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Token Cache
    |--------------------------------------------------------------------------
    |
    | Tokens are cached per account. Leave "store" null to use your default
    | cache store.
    |
    */

    'cache' => [
        'store' => env('BKASH_CACHE_STORE'),
        'prefix' => 'bkash',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Log every API request and response. Useful during bKash merchant
    | validation. Secrets and tokens are masked.
    |
    */

    'log' => [
        'enabled' => env('BKASH_LOG', false),
        'channel' => env('BKASH_LOG_CHANNEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sandbox Validation Routes
    |--------------------------------------------------------------------------
    |
    | Registers /bkash-sandbox/* routes for sandbox validation. Keep this
    | disabled in production.
    |
    */

    'sandbox_routes' => env('BKASH_SANDBOX_ROUTES', false),

    /*
    |--------------------------------------------------------------------------
    | Merchant Accounts
    |--------------------------------------------------------------------------
    |
    | Add as many merchant accounts as you need, then use them with
    | Bkash::account('name').
    |
    */

    'accounts' => [
        'primary' => [
            'sandbox' => env('BKASH_SANDBOX', true),
            'app_key' => env('BKASH_APP_KEY'),
            'app_secret' => env('BKASH_APP_SECRET'),
            'username' => env('BKASH_USERNAME'),
            'password' => env('BKASH_PASSWORD'),
        ],

        // 'secondary' => [
        //     'sandbox' => env('BKASH_SECONDARY_SANDBOX', true),
        //     'app_key' => env('BKASH_SECONDARY_APP_KEY'),
        //     'app_secret' => env('BKASH_SECONDARY_APP_SECRET'),
        //     'username' => env('BKASH_SECONDARY_USERNAME'),
        //     'password' => env('BKASH_SECONDARY_PASSWORD'),
        // ],
    ],

];
