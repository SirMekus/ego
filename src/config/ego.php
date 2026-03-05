<?php

return [
    //Your app's default payment gateway. It must already exist in the 'providers' section below, and has its credentials setup as required.
    'default' => 'paystack',

    'verify_webhook' => env('EGO_VERIFY_WEBHOOK', true), //Whether to verify webhooks or not. If set to false, the package will not verify the authenticity of incoming webhooks. It is recommended to keep this set to true for security reasons.

    'credentials' => [
        'paystack' => [
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
        ],
        'credo' => [
            'secret_key' => env('CREDO_SECRET_KEY'),
        ],
        'flutterwave' => [
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        ],
        'stripe' => [
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'signing_secret' => env('STRIPE_SIGNING_SECRET'),
            'account_id' => env('STRIPE_ACCOUNT_ID'),
            'client_id' => env('STRIPE_CLIENT_ID'),
        ],
    ],

    'providers' => [
        'paystack' => Emmy\Ego\Gateway\Paystack\Paystack::class,
        'flutterwave' => Emmy\Ego\Gateway\Flutterwave\Flutterwave::class,
        'stripe' => Emmy\Ego\Gateway\Stripe\Stripe::class,
        "credo" => Emmy\Ego\Gateway\Credo\Credo::class
    ],
];
