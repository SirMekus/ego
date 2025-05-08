<?php
/**
 * When contributing, I strong encourage a TDD approach.
 * You can just copy and paste this sample test to get started.
 * Of course, you should edit where appropriate.
 * 
 */

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Str;
use Emmy\Ego\Exception\ApiException;
use Emmy\Ego\Factory\PaymentFactory;
use PHPUnit\Framework\Attributes\Test;
use Emmy\Ego\Exception\InvalidRecipientException;

class PaystackPaymentTest extends TestCase
{
    #[Test]
    public function can_make_payment(): void
    {
        $paymentFactory = new PaymentFactory();
        $data = [
            'amount' => 1000,
            'email' => 'Z0m0C@example.com',
            'callback_url' => 'http://localhost/webhook',
            "reference" => Str::random()
        ];

        $response = $paymentFactory->pay($data);
        $this->assertTrue($response['status']);
    }

    #[Test]
    public function cannot_make_payment(): void
    {
        $paymentFactory = new PaymentFactory('your gateway');

        try {
            $paymentFactory->pay([
                'email' => 'mekus',
            ]);
        } catch (ApiException $e) {
            $message = json_decode($e->getMessage(), true);
            $this->assertNotTrue($message['status']);
        }
    }

    #[Test]
    public function cannot_make_payment_with_duplicate_reference(): void
    {
        $paymentFactory = new PaymentFactory('your gateway', config('path.to.your.key'));

        $data = [
            'amount' => 1000,
            'email' => 'Z0m0C@example.com',
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
    public function can_get_supported_banks(): void
    {
        $paymentFactory = new PaymentFactory('your gateway', config('path.to.your.key'));
        $banks = $paymentFactory->getBanks();
        $this->assertArrayHasKey('data', $banks);
    }
    #[Test]
    public function can_make_transfer(): void
    {
        $paymentFactory = new PaymentFactory('your gateway', config('path.to.your.key'));

        $transferStatus = $paymentFactory->transfer(json_decode('{ 
                "type": "nuban",
                "name": "Tolu Robert",
                "account_number": "0000000000",
                "bank_code": "057",
                "currency": "NGN",
                "amount": 1000
                }', true));
        $this->assertArrayHasKey('data', $transferStatus);
    }

    #[Test]
    public function cannot_make_transfer_with_invalid_recipient_code(): void
    {
        $paymentFactory = new PaymentFactory('your gateway', config('path.to.your.key'));

        try{
            $paymentFactory->transfer(json_decode('{ 
                                "type": "nuban",
                                "name": "Tolu Robert",
                                "account_number": "0000000000",
                                "bank_code": "057",
                                "currency": "NGN",
                                "amount": 1000,
                                "recipient_code": "12345"
                                }', true));
        }
        catch(InvalidRecipientException $e){
            $message = json_decode($e->getMessage(), true);
            $this->assertNotTrue($message['status']);
        }
    }

    #[Test]
    public function can_verify_account(): void
    {
        $paymentFactory = new PaymentFactory('your gateway', config('path.to.your.key'));

        $transferStatus = $paymentFactory->verifyAccountNumber(json_decode('{ 
                        "account_number": "0000000000",
                        "bank_code": "057"
                        }', true));
        $this->assertArrayHasKey('data', $transferStatus);
    }

    #[Test]
    public function can_verify_payment(): void
    {
        $paymentFactory = new PaymentFactory('your gateway', config('path.to.your.key'));
        $paymentStatus = $paymentFactory->verifyPayment('BaneWR35kLAaLdBa');
        $this->assertArrayHasKey('data', $paymentStatus);
    }

    #[Test]
    public function can_make_payment_via_authorization_code(): void
    {
        $paymentFactory = new PaymentFactory('your gateway', config('path.to.your.key'));
        $data = [
            'amount' => 1000,
            'email' => 'Z0m0C@example.com',
            'callback_url' => 'http://localhost/webhook',
            "reference" => Str::random(),
            "authorization_code" => "AUTH_hxq8049g4n"
        ];
        $paymentStatus = $paymentFactory->pay($data);
        $this->assertArrayHasKey('api_message', $paymentStatus);
    }
    #[Test]
    public function can_use_magic_methods_to_create_payload(): void
    {
        $paymentFactory = new PaymentFactory('your gateway', config('path.to.your.key'));
        $paymentFactory->setAmount(1000);
        $paymentFactory->setCurrency("NGN");
        $payload = $paymentFactory->getPayload();
        
        $this->assertArrayHasKey('amount', $payload);
        $this->assertArrayHasKey('currency', $payload);
    }
}
