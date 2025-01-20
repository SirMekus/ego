<?php

namespace Emmy\Ego\Factory;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Emmy\Ego\Gateway\Paystack\Paystack;
use Emmy\Ego\Interface\PaymentGatewayInterface;

class PaymentFactory implements PaymentGatewayInterface
{
    protected $paymentGateway;

    public function __construct(
        string|PaymentGatewayInterface $gateway="paystack",
        string|array $key = "")
	{
        if($gateway instanceof PaymentGatewayInterface){
            $this->paymentGateway = $gateway;
        }
        else{
            $this->paymentGateway = match($gateway){
                //subsequent matches can be added here. E.g, Flutterwave, Fincra, Stripe, etc
                default => new Paystack(),
            };
        }
        if($key){
            $this->setKey($key);
        }
    }
    public function getGatewayInstance():PaymentGatewayInterface
    {
        return $this->paymentGateway;
    }
    public function __call($name, $arguments)
	{
		if (str_starts_with($name, 'set')) {
			$property = substr($name, 3);

			$property = Str::snake($property);

			$this->paymentGateway->builder[$property] = $arguments[0];
		} else {
			throw new \RuntimeException(sprintf('Missing %s method.'));
		}
	}
    public function setKey(string|array $key): void
    {
        $this->paymentGateway->setKey($key);
    }
    // public function createConnection(): void
    // {
    //     $this->paymentGateway->createConnection();
    // }
    public function prepareForPayment(array $array): array
    {
        return $this->paymentGateway->pay($array);
    }
    public function pay(array $array=[]): array
    {
        return $this->paymentGateway->pay($array);
    }
    public function transfer(array $data=[]): array
    {
        return $this->paymentGateway->transfer($data);
    }
    public function verifyPayment(array|string $reference=[]): array
    {
        return $this->paymentGateway->verifyPayment($reference);
    }
    public function verifyWebhook(Request $request): void
    {
        $this->paymentGateway->verifyWebhook($request);
    }

    public function handleWebhook(array $payload): array
	{
        return $this->paymentGateway->handleWebhook($payload);
    }
    public function handlePaymentVerification(string $payload): array
	{
        return $this->paymentGateway->verifyByReference($payload);
    }
    public function getBanks(): array
	{
        return $this->paymentGateway->getBanks();
    }
    public function verifyAccountNumber(array $request=[]): array
	{
        return $this->paymentGateway->verifyAccountNumber($request);
    }
}