<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Str;
use Emmy\Ego\Gateway\Credo\Credo;
use Emmy\Ego\Exception\ApiException;
use Emmy\Ego\Factory\PaymentFactory;
use PHPUnit\Framework\Attributes\Test;
use Emmy\Ego\Exception\UnsupportedGatewayMethodException;

class CredoPaymentTest extends TestCase
{

    #[Test]
    /**
     * @see https://docs.credocentral.com/guides/api-reference/standard for all request parameters.
     * @return void
     */
    public function can_make_payment(): void
    {
        $paymentFactory = new PaymentFactory(Credo::class);
        $data = [
            'amount' => 50000, //Required
            "bearer" => 0, //Optional. 0 = Customer bears fee; 1 = Merchant bears fee. Default is 0.
            'email' => 'user@example.com', //Required.
            'callback_url' => 'http://localhost/webhook', //Optional.
            "reference" => Str::random() //Optional.
        ];

        $response = $paymentFactory->pay($data);
        $this->assertTrue($response['status']);
    }

    #[Test]
    public function cannot_make_payment_without_amount(): void
    {
        $paymentFactory = new PaymentFactory(Credo::class);

        try {
            $paymentFactory->pay([
                'email' => 'user@example.com',
            ]);
        } catch (ApiException $e) {
            $message = json_decode($e->getMessage(), true);
            $this->assertNotTrue($message['status']);
        }
    }

    #[Test]
    public function cannot_make_payment_without_email(): void
    {
        $paymentFactory = new PaymentFactory(config("ego.credentials.credo_secret_key"));

        try {
            $paymentFactory->pay([
                'amount' => 1000,
            ]);
        } catch (ApiException $e) {
            $message = json_decode($e->getMessage(), true);
            $this->assertNotTrue($message['status']);
        }
    }


    #[Test]
    public function cannot_make_payment_with_duplicate_reference(): void
    {
        $paymentFactory = new PaymentFactory(Credo::class);

        $data = [
            'amount' => 1000,
            'email' => 'user@example.com',
            'reference' => Str::random(),
            'callback_url' => 'http://localhost/webhook',
            'metadata' => [
                'order_id' => '12345',
            ],
        ];

        $response = $paymentFactory->pay($data);
        $this->assertTrue($response['status']);

        try {
            $paymentFactory->pay($data);
        } catch (ApiException $e) {
            $message = json_decode($e->getMessage(), true);
            $this->assertNotTrue($message['status']);
        }
    }

    #[Test]
    public function cannot_use_get_banks_method()
    {
        $paymentFactory = new PaymentFactory(Credo::class);

        try {
            $banks = $paymentFactory->getBanks();
            $this->assertArrayHasKey('data', $banks);
        } catch (UnsupportedGatewayMethodException $e) {
            $message = $e->getMessage();
            $this->assertNotTrue($message['status']);
        }
    }

    #[Test]
    public function cannot_use_the_transfer_method(): void
    {
        $paymentFactory = new PaymentFactory(Credo::class);

        try {
            $transferStatus = $paymentFactory->transfer(json_decode('{ 
                "type": "nuban",
                "name": "Tolu Robert",
                "account_number": "0000000000",
                "bank_code": "057",
                "currency": "NGN",
                "amount": 1000
                }', true));
            $this->assertArrayHasKey('data', $transferStatus);
        } catch (UnsupportedGatewayMethodException $e) {
            $message = $e->getMessage();
            $this->assertNotTrue($message["status"]);
        }
    }

    #[Test]
    public function can_use_magic_methods_to_create_payload(): void
    {
        $paymentFactory = new PaymentFactory(Credo::class);
        $paymentFactory->setAmount(1000);
        $paymentFactory->setCurrency("NGN");
        $paymentFactory->setCustomerFirstNmae("Alice");
        $paymentFactory->setCustomerLastName("Bob");
        $payload = $paymentFactory->getPayload();

        $this->assertArrayHasKey('amount', $payload);
        $this->assertArrayHasKey('currency', $payload);
        $this->assertArrayHasKey('customerFirstName', $payload);
        $this->assertArrayHasKey('customerLastName', $payload);
    }

    #[Test]
    public function can_verify_payment(): void
    {
        $paymentFactory = new PaymentFactory(config("ego.credentials.credo_secret_key"));
        $paymentStatus = $paymentFactory->verifyPayment('BaneWR35kLAaLdBa');
        $this->assertArrayHasKey('data', $paymentStatus);
    }
}
