## About Ego

Ego ("money") is an all-in-one payment gateway library for the PHP (Laravel) community. It is designed to bring together all the possible payment gateways under one "umbrella" via a defined set of interface covering regular/day-to-day business/user goals. C

With this library, you don't need to worry about switching between different payment gateways; just check it up here, and if it's available, via the guaranteed set of interfaces, use it straight up as it covers simple everyday use cases. 

For something more complex, you should consider using your preferred gateway's SDK (if available), extending our implementation or creating a new implementation entirely for your use.

> For this, I strongly encourage contributions, please. If you have ever worked with/on a particular payment gateway, please contribute by adding it here for other developers to use. Thank you.

# Table of Contents

- [Getting Started](#getting-started)
- [Available Interface Methods](#available-interface-methods)
- Available (Underlying) Gateway(s)
    - [Paystack](#paystack) 
        - [Special Cases](#special-cases) 
    - [Flutterwave](#flutterwave) 
- [Contributing](#contributing)

> **This documentation will constantly be updated as more interfaces/methods or payment gateways are added.**

## Getting Started

Install the package like so:

 ```bash
composer require sirmekus/ego
 ```
 This library obscures the underlying payment gateway by providing, instead, a "factory" for you to interact with. This "factory" also contains the common methods (interface) all the available payment gateways (here) should have so you can still interact with the payment gateway. 

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
            "reference" => "randomized"
        ];
$response = $paymentFactory->pay($data);
 ```

 The `PaymentFactory` class accepts two optional parameters: 
 - A `PaymentGateway` interface (or string indicating which payment gateway to use)
 - A "configuration" key which specifies how the underlying payment gateway shall be configured (for authentication) to hit the appropriate API. 
 
 If none is specified, the default - gotten from the config file - is used.

## Available Interface Methods

```php
    //Sets the configuration for the underlying payment gateway (if necessary)
    public function setKey(string|array $key):void;

    //Helps to create the appropriate payload when you pass it an array containing the values the target gateway expects. The underlying payment gateway class will determine how many of the 'important' payloads it will set.
    public function prepareForPayment(array $data): array;

    //To make a payment or deposit
    public function pay(array $array): array;

    //To verify a payment or deposit
    public function verifyPayment(array|string $array): array;

    //To verify a webhook. You can use it as-is in your application's webhook endpoint. If the webhook is valid, it will continue to execute your script else it fails with a 404.
    public function verifyWebhook(Request $request): void;

    //After verifying your webhook, it can then run another verification of payment. The value it returns is dependent on the underlying payment gateway.
    public function handleWebhook(array $request): array;

    //To fetch a list of available banks the underlying payment gateway supports
    public function getBanks(string $countryCode=""): array;

    //Verifies an account number
    public function verifyAccountNumber(array $request): array;

    //To run a transfer/withdrawal transaction based on the payment gateway
    public function transfer(array $data): array;

    //If the magic method is used to craft a payload/request, the crafted payload is returned
    public function getPayload():array;
```

All of these methods are guaranteed to be accessible regardless of the payment gateway in use. However, the user should know what payload parameters his/her chosen payment gateway expects and pass that parameter to the library when neccessary.

Also, instead of manually crafting or passing the paramters, you can "build" them. For example, if your payment gateway expects: amount, currency, metadata and reference, you can build them like so:
```php
 $paymentFactory = new PaymentFactory();
 $paymentFactory->setAmount($amount);
 $paymentFactory->setCurrency($currency);
 $paymentFactory->setReference($reference);
 $paymentFactory->setMetadata($metadata);

 $response = $paymentFactory->pay();
```

Methods starting with the `set` keyword are 'magical', and represents a 'payload item'. The parameter acts as the value. When the `pay` method is then called without any parameter, it takes from the payload already built using the magic methods.

Alternatively, if you already have an array (say from submitted form data), instead of building the payload, you can just dump it into the library via the `prepareForPayment()` method and the library will automatically build it for you. Even if the array is nested, it will fetch the first matching key/value pair required to create a request payload. This means that a model (that has been turned into an array can be passed to it as well).

>NB: How the payload is built is dependent on the underlying payment gateway. A gateway may require 5 parameters while the contributor of the particular payment gateway feature (in this package) may just cater for 2 in the `prepareForPayment()` method. If the remaining 3 are important, it is recommended you manually set the payload instead.

 E.g:
```php 
$paymentFactory = new PaymentFactory();
//Assuming there is a request (from client which has been validated of course) made as a Request object in Laravel
$gateway = $paymentFactory->prepareForPayment($request->validated());
$response = $paymentFactory->pay();
```

The method wil extract the 'minimal' request parameters (or payload) needed to interact with the API endpoint of your preferred service provider or payment gateway.

However, it is possible that you might want to interact with the underlying payment gateway class directly. This may be because the payment gateway class (integrated in this package) adds some method(s) that may not be available in the general interface above. 

For instance, if the underlying payment gateway class has a method `createInvoice` which is not defined in the above interface, it can't be accessed directly from the `PaymentFactory` class. You can do this like so instead:

```php
$paymentFactory = new PaymentFactory();
$gateway = $paymentFactory->getGatewayInstance();
//Now you can use the actual payment gateway class
$gateway->createInvoice();
//continue operation
```

If you have a gateway class you already use with some important methods you may have already created, you can swap our implementation with yours in the 'providers' section of the **ego.php** config file. But it must implement the **'PaymentGatewayInterface'**. One way to do it is to extend our own class.

The typical structure of the **ego.php** config file is shown below:

```php
return [
    //Your app's default payment gateway. It must already exist in the 'providers' section below, and has its credentials setup as required.
    'default' => 'paystack',

    'credentials' => [
        'paystack' => [
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        ],
        'flutterwave' => [
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
            'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
        ],
    ],

    'providers' => [
        'paystack' => Emmy\Ego\Gateway\Paystack\Paystack::class,
        'flutterwave' => Emmy\Ego\Gateway\Flutterwave\Flutterwave::class,
    ],
];
```

# Available (Underlying) Payment Gateway(s)
## Paystack

Once you know the typical request parameters expected by [Paystack](https://paystack.com/docs/api), you can just plug them in directly into the appropriate method discussed above and use it straight away.

The following methods are available for Paystack in this package:
- All the methods defined in the interface

## Special Cases

### Case 1:

When using the `prepareForPayment($array)` method, the following will be extracted from the array passed in as a parameter (so make sure they're set at least):
- email
- amount
- currency
- channels
- callback_url (or 'callbackUrl')
- bearer
- metadata
- reference

### Case 2:
In addition, on Paystack, you can actually charge customers via directing them to an [authorization URL](https://paystack.com/docs/api/transaction/#initialize) or charging them directly via an [authorization code](https://paystack.com/docs/api/transaction/#charge-authorization). You don't need to worry about any of these when using the package; the `pay()` method is all you need to make any transaction or payment. 

If you want to charge a customer via an authorization code, in your payload/array, set a key with the name **'authorization_code'**. The default is the former where a link will be created and then you redirect the user to the authorization link.

### Case 3:
On paystack, when making transfer/withdrawal to a bank account, you need to first create a [transfer recipient](https://paystack.com/docs/api/transfer-recipient/#create) which will create a unique code for you with which you can then make the [transfer](https://paystack.com/docs/api/transfer/#initiate) to the user/customer. 

This process has been taken care of already when you simply use the `transfer()` method. You don't need to worry about it.

However, if you already have the 'transfer recipient code' created, simply pass it as inside your payload and the package will extract it automatically. Now instead of first creating the transfer recipient on your behalf, it will just make the 'transfer' directly.

## Flutterwave
Once you know the typical request parameters required by [Flutterwave](https://developer.flutterwave.com/reference/introduction-1), you can just plug them in directly into the appropriate method discussed above and use it straight away.

The following methods are available for Flutterwave in this package:
- All the methods defined in the interface


## Contributing

Please check the **'contrib'** directory for more information.
