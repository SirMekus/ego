<?php

namespace Emmy\Ego\Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Str;
use Emmy\Ego\Exception\ApiException;
use Emmy\Ego\Factory\PaymentFactory;
use PHPUnit\Framework\Attributes\Test;

class NombaPaymentTest extends TestCase
{
    #[Test]
    public function can_make_payment(): void
    {
        $paymentFactory = new PaymentFactory('nomba');

        $data = [
            'amount' => 1000,
            'email' => 'Z0m0C@example.com',
            'currency' => 'NGN',
            'callback_url' => 'http://example.com',
            'reference' => Str::uuid(),
        ];
        $paymentFactory->prepareForPayment($data);

        $response = $paymentFactory->pay();
        $this->assertTrue($response['status']);
        $this->assertArrayHasKey('url', $response);
    }

    #[Test]
    public function cannot_make_payment(): void
    {
        $paymentFactory = new PaymentFactory('nomba', 'invalid_key');

        try {
            $paymentFactory->pay([
                'amount' => 1000,
                'email' => 'test@example.com',
                'currency' => 'NGN',
                'reference' => Str::random(),
            ]);
        } catch (ApiException $e) {
            $message = json_decode($e->getMessage(), true);
            $this->assertNotTrue($message['status']);
        }
    }

    #[Test]
    public function can_get_supported_banks(): void
    {
        $paymentFactory = new PaymentFactory('nomba');
        $banks = $paymentFactory->getBanks();
        $this->assertArrayHasKey('data', $banks);
    }

    #[Test]
    public function can_make_transfer(): void
    {
        $paymentFactory = new PaymentFactory('nomba');
        $payload = [
            'accountNumber' => '0690000040',
            'bankCode' => '044',
            'accountName' => 'Tolu Robert',
            'amount' => 5500,
            'narration' => 'Test transfer',
            'senderName' => 'Emmy Ego',
            'merchantTxRef' => Str::random(),
        ];
        $paymentFactory->prepareForTransfer($payload);

        $transferStatus = $paymentFactory->transfer();

        $this->assertTrue($transferStatus['success']);
        $this->assertArrayHasKey('data', $transferStatus);
    }

    #[Test]
    public function can_verify_account(): void
    {
        $paymentFactory = new PaymentFactory('nomba');

        $result = $paymentFactory->verifyAccountNumber([
            'accountNumber' => '0690000032',
            'bankCode' => '044',
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('accountName', $result);
        $this->assertArrayHasKey('accountNumber', $result);
    }

    #[Test]
    public function cannot_verify_account_without_required_fields(): void
    {
        $paymentFactory = new PaymentFactory('nomba');

        $this->expectException(\Exception::class);

        $paymentFactory->verifyAccountNumber([]);
    }

    #[Test]
    public function can_verify_deposit_payment(): void
    {
        $paymentFactory = new PaymentFactory('nomba');
        $paymentStatus = $paymentFactory->verifyPayment('NOMBA_ORDER_REF_12345');
        $this->assertArrayHasKey('data', $paymentStatus);
        $this->assertArrayHasKey('status', $paymentStatus);
        $this->assertArrayHasKey('reference', $paymentStatus);
    }

     #[Test]
    public function can_verify_transfer_payment(): void
    {
        $paymentFactory = new PaymentFactory('nomba');
        $paymentStatus = $paymentFactory->verifyPayment('c4307d58-2513-41d8-b7f7-dfecd5f9fdbe', 'transfer');
        $this->assertArrayHasKey('data', $paymentStatus);
        $this->assertArrayHasKey('status', $paymentStatus);
        $this->assertArrayHasKey('reference', $paymentStatus);
    }

    #[Test]
    public function can_make_tokenized_payment(): void
    {
        $paymentFactory = new PaymentFactory('nomba');

        $data = [
            'amount' => 1000,
            'email' => 'Z0m0C@example.com',
            'currency' => 'NGN',
            'callback_url' => 'http://example.com',
            'reference' => Str::uuid(),
            'token' => "1234567890",
        ];
        $paymentFactory->prepareForPayment($data);

        $response = $paymentFactory->pay();
        $this->assertTrue($response['status']);
        $this->assertEquals('Charge attempted', $response['message']);
        $this->assertArrayHasKey('api_message', $response);
        $this->assertArrayNotHasKey('url', $response);
    }

    #[Test]
    public function can_use_magic_methods_to_create_payload(): void
    {
        $paymentFactory = new PaymentFactory('nomba');
        $paymentFactory->setAmount(1000);
        $paymentFactory->setCurrency('NGN');
        $payload = $paymentFactory->getPayload();

        $this->assertArrayHasKey('amount', $payload);
        $this->assertArrayHasKey('currency', $payload);
    }
}
