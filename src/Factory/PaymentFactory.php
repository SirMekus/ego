<?php

namespace Emmy\Ego\Factory;

use Illuminate\Http\Request;
use Emmy\Ego\Interface\PaymentGatewayInterface;
use Emmy\Ego\Exception\UnspportedGatewayException;

class PaymentFactory implements PaymentGatewayInterface
{
    protected $paymentGateway;

    public function __construct(
        string|PaymentGatewayInterface $gateway="",
        string|array $key = "")
	{
        if($gateway instanceof PaymentGatewayInterface){
            $this->paymentGateway = $gateway;
        }
        else{
            if($gateway){
                $gateway = config("ego.providers.$gateway");
                if(!$gateway){
                    throw new UnspportedGatewayException('Gateway not supported. If you can, please consider contributing to this package to enable it here.');
                }
            }
            else{
                $gateway = config("ego.providers.".config("ego.default"));
            }
            $this->paymentGateway = new $gateway();
        }
        if($key){
            $this->setKey($key);
        }
    }
    public function getGatewayInstance()
    {
        return $this->paymentGateway;
    }
    public function __call($name, $arguments)
	{
        return $this->paymentGateway->$name(...$arguments);
	}
    public function setKey(string|array $key): void
    {
        $this->paymentGateway->setKey($key);
    }
    public function getPayload():array
	{
		return $this->paymentGateway->getPayload();
	}
    public function prepareForPayment(array $array): array
    {
        return $this->paymentGateway->prepareForPayment($array);
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
    public function getBanks(string $countryCode=""): array
	{
        return $this->paymentGateway->getBanks($countryCode);
    }
    public function verifyAccountNumber(array $request=[]): array
	{
        return $this->paymentGateway->verifyAccountNumber($request);
    }
}