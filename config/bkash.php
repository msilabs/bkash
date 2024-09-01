<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base Url
    |--------------------------------------------------------------------------
    */

    'base_url' => 'https://tokenized.pay.bka.sh/v1.2.0-beta',

    /*
    |--------------------------------------------------------------------------
    | Sandbox Url
    |--------------------------------------------------------------------------
    */

    'sandbox_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',

    /*
    |--------------------------------------------------------------------------
    | bKash accounts
    |--------------------------------------------------------------------------
    */

    "accounts" => [
        "primary" => [
            "sandbox"       => env("BKASH_SANDBOX", true),  #for production use false
            "app_key"       => env("BKASH_APP_KEY"),
            "app_secret"    => env("BKASH_APP_SECRET"),
            "username"      => env("BKASH_USERNAME"),
            "password"      => env("BKASH_PASSWORD"),
        ],
        // Add more stores if you need
    ],
];