<?php

declare(strict_types=1);

namespace Emmy\Ego\Interface;

use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
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
    public function getBanks(): array;

    //Verifies an account number
    public function verifyAccountNumber(array $request): array;

    //To run a transfer/withdrawal transaction based on the payment gateway
    public function transfer(array $data): array;

    //If the magic method is used to craft a payload/request, the crafted payload is returned
    public function getPayload():array;
}
