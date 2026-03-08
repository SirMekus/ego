## About Ego

Ego ("money") is an all-in-one payment gateway library for the PHP (Laravel) community. It is designed to bring together all the possible payment gateways under one "umbrella" via a defined set of interfaces covering regular/day-to-day business/user goals.

With this library, you don't need to worry about switching between different payment gateways; just check it up here, and if it's available, via the guaranteed set of interfaces, use it straight away as it covers simple everyday use cases.

**Note that the expected parameters required by your gateway of choice, when making a request via this package, should be sent as well**.

For something more complex, you should consider using your preferred gateway's SDK (if available), extending our implementation, or creating a new implementation entirely for your use.

> For this, I strongly encourage contributions, please. If you have ever worked with/on a particular payment gateway, please contribute by adding it here for other developers to use. Thank you.

# Table of Contents

- [Getting Started](#getting-started)
- [Available Interface Methods](#available-interface-methods)
- Available (Underlying) Gateway(s)
    - [Paystack](#paystack)
        - [Special Cases](#paystack-special-cases)
    - [Flutterwave](#flutterwave)
    - [Stripe](#stripe)
        - [Special Cases](#stripe-special-cases)
    - [Nomba](#nomba)
        - [Configuration](#nomba-configuration)
        - [Authentication](#nomba-authentication)
        - [Special Cases](#nomba-special-cases)
- [Webhook Strategy](#webhook-strategy)
- [Contributing](#contributing)

> **This documentation will constantly be updated as more interfaces/methods or payment gateways are added.**

## Getting Started

Install the package like so:

```bash
composer require sirmekus/ego
```

This library obscures the underlying payment gateway by providing, instead, a "factory" for you to interact with. This "factory" also contains the common methods (interface) all the available payment gateways (here) should have, so you can still interact with the payment gateway.

To publish the default config file to customize, run:

```bash
php artisan vendor:publish --provider="Emmy\Ego\Provider\EgoProvider"
```

Example usage:

```php
$paymentFactory = new PaymentFactory();
$data = [
    'amount' => 1000,
    'email' => 'Z0m0C@example.com',
    'callback_url' => 'http://localhost/webhook',
    'reference' => 'randomized',
];
$response = $paymentFactory->pay($data);
```

The `PaymentFactory` class accepts two optional parameters:
- A `PaymentGateway` interface (or string indicating which payment gateway to use)
- A "configuration" key which specifies how the underlying payment gateway shall be configured (for authentication) to hit the appropriate API.

If none is specified, the default — gotten from the config file — is used.

## Available Interface Methods

```php
// Sets the configuration/credentials for the underlying payment gateway
public function setKey(string|array $key): void;

// Builds the appropriate payload from an array of values the target gateway expects.
// The underlying payment gateway class determines which fields it extracts.
public function prepareForPayment(array $data): array;

// Builds the appropriate transfer payload from an array of values.
public function prepareForTransfer(array $data): array;

// Initiates a payment or deposit
public function pay(array $array): array;

/**
 * Verifies a payment, deposit, or transfer. The return value is dependent on
 * the underlying payment gateway.
 *
 * $paymentType is optional and can be defined by your implementation. Enums are
 * intentionally avoided so each implementation can define its own. An error will
 * typically be thrown if the payment type is not supported.
 */
public function verifyPayment(array|string $array, ?string $paymentType = null): array;

// Verifies an incoming webhook. If the webhook is valid, execution continues;
// otherwise it fails with a 401/404 response.
public function verifyWebhook(Request $request): void;

// Fetches a list of banks supported by the underlying payment gateway
public function getBanks(string $countryCode = ""): array;

// Verifies an account number
public function verifyAccountNumber(array $request): array;

// Runs a transfer/withdrawal transaction
public function transfer(array $data): array;

// Returns the crafted payload if magic methods were used to build it
public function getPayload(): array;
```

All of these methods are guaranteed to be accessible regardless of the payment gateway in use. However, you should know what payload parameters your chosen payment gateway expects and pass them when necessary.

Instead of manually crafting parameters, you can "build" them. For example, if your payment gateway expects: amount, currency, metadata, and reference, you can build them like so:

```php
$paymentFactory = new PaymentFactory();
$paymentFactory->setAmount($amount);
$paymentFactory->setCurrency($currency);
$paymentFactory->setReference($reference);
$paymentFactory->setMetadata($metadata);

$response = $paymentFactory->pay();
```

Methods starting with the `set` keyword are 'magical' and represent a payload item. The parameter acts as the value. When `pay()` or `transfer()` is called without any parameter, it takes from the payload already built using the magic methods.

Alternatively, if you already have an array (say from submitted form data), instead of building the payload manually, you can dump it into the library via `prepareForPayment()` or `prepareForTransfer()` and the library will automatically build it for you. Even if the array is nested, it will fetch the first matching key/value pair required to create a request payload. This means a model (cast to an array) can be passed to it as well.

> **NB:** How the payload is built is dependent on the underlying payment gateway. A gateway may require 5 parameters while the contributor of that particular gateway (in this package) may only cater for 2 in `prepareForPayment()` or `prepareForTransfer()`. If the remaining parameters are important, it is recommended you set the payload manually instead.

Example:

```php
$paymentFactory = new PaymentFactory();
// Assuming a validated Laravel request
$paymentFactory->prepareForPayment($request->validated());
$response = $paymentFactory->pay();
```

The method will extract the minimal request parameters (or payload) needed to interact with the API endpoint of your preferred service provider or payment gateway.

### Accessing Gateway-Specific Methods

If you need to call a method on the underlying gateway that is not part of the general interface, you can access the gateway instance directly:

```php
$paymentFactory = new PaymentFactory();
$gateway = $paymentFactory->getGatewayInstance();
// Now you can use the actual payment gateway class
$gateway->someGatewaySpecificMethod();
```

### Swapping Gateway Implementations

If you have a gateway class with custom logic, you can swap our implementation with yours in the `providers` section of the **ego.php** config file. It must implement `PaymentGatewayInterface`. One way to do it is to extend our class and override the methods you need.

### Config File Structure

The typical structure of the **ego.php** config file is shown below. After publishing it, add the relevant environment variables to your `.env` file.

```php
return [
    // Your app's default payment gateway. Must exist in the 'providers' section
    // below and have its credentials set up.
    'default' => 'paystack',

    // Whether to verify webhook authenticity. Recommended to keep true.
    'verify_webhook' => env('EGO_VERIFY_WEBHOOK', true),

    'credentials' => [
        'paystack' => [
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
        ],
        'flutterwave' => [
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        ],
        'stripe' => [
            'secret_key'      => env('STRIPE_SECRET_KEY'),
            'signing_secret'  => env('STRIPE_SIGNING_SECRET'),
            'account_id'      => env('STRIPE_ACCOUNT_ID'),
            'client_id'       => env('STRIPE_CLIENT_ID'),
        ],
        'nomba' => [
            'client_id'     => env('NOMBA_CLIENT_ID'),
            'secret_key'    => env('NOMBA_SECRET_KEY'),
            'account_id'    => env('NOMBA_ACCOUNT_ID'),
            'signature_key' => env('NOMBA_SIGNATURE_KEY'),
            'base_url'      => env('NOMBA_BASE_URL'),
        ],
    ],

    'providers' => [
        'paystack'    => Emmy\Ego\Gateway\Paystack\Paystack::class,
        'flutterwave' => Emmy\Ego\Gateway\Flutterwave\Flutterwave::class,
        'stripe'      => Emmy\Ego\Gateway\Stripe\Stripe::class,
        'nomba'       => Emmy\Ego\Gateway\Nomba\Nomba::class,
    ],
];
```

---

# Available (Underlying) Payment Gateway(s)

## Paystack

Once you know the typical request parameters expected by [Paystack](https://paystack.com/docs/api), you can plug them in directly into the appropriate method discussed above and use it straight away.

The following methods are available for Paystack in this package:
- All the methods defined in the interface

### Paystack Special Cases

#### Case 1: Payment payload fields

When using `prepareForPayment($array)`, the following will be extracted from the array passed in as a parameter:
- `email`
- `amount`
- `currency`
- `channels`
- `callback_url` (or `callbackUrl`)
- `bearer`
- `metadata` (or `metaData`)
- `reference`

When using `prepareForTransfer($array)`, the following will be extracted:
- `recipient_type`
- `account_name`
- `account_number`
- `bank_code`
- `reference`
- `amount`
- `description`

#### Case 2: Authorization URL vs Authorization Code

On Paystack, you can charge customers by directing them to an [authorization URL](https://paystack.com/docs/api/transaction/#initialize) or by charging them directly via an [authorization code](https://paystack.com/docs/api/transaction/#charge-authorization). You don't need to worry about these details when using this package — the `pay()` method handles both.

To charge a customer via an authorization code, include a key named `authorization_code` in your payload/array. The default behaviour (when no authorization code is present) is to create a checkout link and redirect the user.

#### Case 3: Transfers

On Paystack, making a transfer/withdrawal to a bank account requires first creating a [transfer recipient](https://paystack.com/docs/api/transfer-recipient/#create), which generates a unique code used to [initiate the transfer](https://paystack.com/docs/api/transfer/#initiate).

This process is handled automatically when you use the `transfer()` method - you don't need to worry about it.

However, if you already have a transfer recipient code, simply include it in your payload as `recipient_code` and the package will skip the recipient creation step and initiate the transfer directly.

---

## Flutterwave

Once you know the typical request parameters required by [Flutterwave](https://developer.flutterwave.com/reference/introduction-1), you can plug them in directly into the appropriate method and use it straight away.

The following methods are available for Flutterwave in this package:
- All the methods defined in the interface

When using `prepareForPayment($array)`, the following will be extracted:
- `email`
- `amount`
- `currency`
- `tx_ref` (or `reference`)
- `redirect_url` (or `callback_url` / `callbackUrl`)
- `metadata` (or `metaData`)

---

## Stripe

Once you know the typical request parameters required by [Stripe](https://docs.stripe.com/api?lang=curl), you can plug them in directly into the appropriate method and use it straight away.

The following methods are available for Stripe in this package:
- All the methods defined in the interface

### Stripe Special Cases

#### Case 1: Payment payload fields

When using `prepareForPayment($array)`, the following will be extracted:
- `email` (or `customer_email`)
- `amount`
- `currency` — defaults to `usd` if not provided
- `mode` — defaults to `payment` if not provided
- `description` — used as the product name; defaults to `"Account Funding"`
- `quantity` — defaults to `1`
- `tx_ref` (or `reference`) — mapped to `client_reference_id`
- `redirect_url` (or `callback_url` / `callbackUrl`) — mapped to `success_url`

#### Case 2: Transfers

Stripe transfers are sent to connected Stripe accounts (not directly to bank accounts). You must provide a `destination` (or `destination_id`) in your payload, which is the ID of the connected Stripe account to transfer funds to.

#### Case 3: Webhook verification

Stripe webhook verification requires a `signing_secret` configured in `ego.credentials.stripe.signing_secret`. Set the `STRIPE_SIGNING_SECRET` environment variable to the signing secret from your Stripe dashboard.

---

## Nomba

[Nomba](https://nomba.com) is a Nigerian payment infrastructure provider. Once you know the typical request parameters it expects, you can plug them in directly and use it straight away.

The following methods are available for Nomba in this package:
- All the methods defined in the interface

### Nomba Configuration

Add the following environment variables to your `.env` file:

```dotenv
NOMBA_CLIENT_ID=your_client_id
NOMBA_SECRET_KEY=your_client_secret
NOMBA_ACCOUNT_ID=your_account_id
NOMBA_SIGNATURE_KEY=your_webhook_signature_key
NOMBA_BASE_URL=https://api.nomba.com/v1/
```

All five values can be found in or generated from your [Nomba Dashboard](https://dashboard.nomba.com).

| Variable              | Description                                                              |
|-----------------------|--------------------------------------------------------------------------|
| `NOMBA_CLIENT_ID`     | Your OAuth2 client ID                                                    |
| `NOMBA_SECRET_KEY`    | Your OAuth2 client secret                                                |
| `NOMBA_ACCOUNT_ID`    | Your Nomba parent account ID                                             |
| `NOMBA_SIGNATURE_KEY` | The secret key used to verify incoming webhook payloads                  |
| `NOMBA_BASE_URL`      | The Nomba API base URL (e.g. `https://api.nomba.com/v1/`)                |

### Nomba Authentication

Nomba uses **OAuth2 client credentials** for authentication. The package handles this automatically - you do not need to manage tokens yourself. When a request is made, the package:

1. Checks Laravel's cache for a valid access token.
2. If none exists (or it has expired), it requests a new one from Nomba using your `client_id` and `secret_key`.
3. Caches the new token for slightly less than its TTL to avoid edge-case expiry.

This means your app stays authenticated transparently across requests with zero manual token management.

### Nomba Special Cases

#### Case 1: Payment payload fields

When using `prepareForPayment($array)`, the following will be extracted:

| Key(s) in your array                           | Description                                           |
|------------------------------------------------|-------------------------------------------------------|
| `email`                                        | Customer's email address                              |
| `customerId` / `customer_id` / `email`         | Customer identifier (falls back to email if absent)   |
| `amount`                                       | Amount to charge                                      |
| `currency`                                     | Currency code (e.g. `NGN`)                            |
| `reference`                                    | Your unique order reference                           |
| `callback_url` / `callbackUrl`                 | URL to redirect to after payment                      |
| `token`                                        | Tokenized card key (triggers direct card charge)      |
| `tokenize`                                     | Whether to tokenize the card — defaults to `true`     |

#### Case 2: Authorization URL vs Tokenized Card Payment

Similar to Paystack, Nomba supports two payment flows:

- **Authorization URL (default):** A checkout link is created and the customer is redirected to it to complete payment. This is the default behaviour.
- **Tokenized Card Payment:** If you include a `token` key in your payload (a card token previously issued by Nomba), the customer is charged directly without a redirect.

The `pay()` method handles both flows automatically. The presence of a `token` in the payload is what triggers the direct charge.

```php
// Default: redirect to checkout page
$paymentFactory = new PaymentFactory('nomba');
$response = $paymentFactory->pay([
    'email'        => 'customer@example.com',
    'amount'       => 5000,
    'currency'     => 'NGN',
    'reference'    => 'unique-ref-001',
    'callback_url' => 'https://yourapp.com/payment/callback',
]);
// $response['url'] contains the checkout link

// Tokenized: charge card directly
$response = $paymentFactory->pay([
    'email'     => 'customer@example.com',
    'amount'    => 5000,
    'currency'  => 'NGN',
    'reference' => 'unique-ref-002',
    'token'     => 'card-token-from-nomba',
]);
```

#### Case 3: Verifying Payments

`verifyPayment()` accepts an optional `$paymentType` parameter to distinguish between different transaction types:

| `$paymentType` value         | Description                              |
|------------------------------|------------------------------------------|
| `transaction` or `deposit`   | Verify a customer payment/deposit        |
| `transfer` or `bank_transfer`| Verify an outbound bank transfer         |

```php
// Verify a customer payment
$status = $paymentFactory->verifyPayment('unique-ref-001', 'transaction');

// Verify a bank transfer
$status = $paymentFactory->verifyPayment('unique-ref-002', 'bank_transfer');
```

The returned array has the following structure:

```php
[
    'status'    => 'success' | 'pending' | 'failed',
    'message'   => 'Transaction description or narration',
    'data'      => [...],  // Full transaction data from Nomba
    'reference' => 'order-reference-string',
]
```

#### Case 4: Transfers

When using `prepareForTransfer($array)`, the following will be extracted:

| Key(s) in your array               | Description                          |
|------------------------------------|--------------------------------------|
| `accountNumber` / `account_number` | Recipient's bank account number      |
| `amount`                           | Amount to transfer                   |
| `accountName` / `account_name`     | Recipient's account name             |
| `bankCode` / `bank_code`           | Recipient's bank code                |
| `narration` / `description`        | Transfer narration                   |
| `senderName` / `sender_name`       | Name of the sender                   |
| `merchantTxRef` / `reference`      | Your unique transaction reference    |

```php
$paymentFactory = new PaymentFactory('nomba');
$response = $paymentFactory->transfer([
    'account_number' => '0123456789',
    'account_name'   => 'John Doe',
    'bank_code'      => '058',
    'amount'         => 10000,
    'narration'      => 'Payment for services',
    'sender_name'    => 'My Business',
    'reference'      => 'transfer-ref-001',
]);
```

#### Case 5: Account Number Lookup

Before making a transfer, you can verify a bank account number to confirm the account name:

```php
$result = $paymentFactory->verifyAccountNumber([
    'accountNumber' => '0123456789',
    'bankCode'      => '058',
]);

// Returns:
// [
//     'success'       => true,
//     'accountNumber' => '0123456789',
//     'bankCode'      => '058',
//     'accountName'   => 'John Doe',
// ]
```

Both `accountNumber` and `bankCode` are required. An exception is thrown if either is missing.

#### Case 6: Webhook Verification

Nomba signs its webhook payloads using HMAC-SHA256. The package verifies the signature automatically when you call `verifyWebhook()`. Ensure `NOMBA_SIGNATURE_KEY` is set in your `.env` file — this is the webhook signing secret from your Nomba dashboard.

The verification process:
1. Extracts key fields from the webhook payload (`event_type`, `requestId`, merchant and transaction details).
2. Concatenates them with the `nomba-timestamp` header value.
3. Computes an HMAC-SHA256 hash using your signature key and compares it against the `nomba-signature` header.

If the signatures do not match, an `ApiException` is thrown.

---

## Webhook Strategy

Since this package implements a method that verifies webhook requests/payloads, one way of using the same route for all supported payment gateways is shown below:

### Step 1: Create a dynamic route

```php
// web.php
Route::post('money/na/water/webhook/{gateway}', App\Http\Controllers\Dashboard\WebhookController::class);
```

> The value assigned to the dynamic part of the URL above should match any of the supported payment gateways listed in the `providers` section of your `ego.php` config file.

### Step 2: Handle the payload

```php
class WebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway)
    {
        $gateway = new PaymentFactory($gateway);
        $gateway->verifyWebhook($request);

        $payload = $request->json()->all();

        // Handle the payload. Ideally, fire an event to avoid blocking
        // on long-running tasks.

        return response()->json();
    }
}
```

With this, you have a single route that can handle any webhook. If you move from Gateway A to B, you won't have to create a new route or implement new gateway-specific handling.

---

## Contributing

Please check the **'contrib'** directory for more information. I really appreciate you for doing this.
