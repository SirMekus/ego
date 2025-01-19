<?php

declare(strict_types=1);

namespace Emmy\Ego\Interface;

use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    public function setKey(string|array $key):void;
    public function createConnection():void;
    public function pay(array $array): array;
    public function verifyPayment(array|string $array): array;
    public function verifyWebhook(Request $request): void;
    public function handleWebhook(array $request): array;
    public function getBanks(): array;
    public function verifyAccountNumber(array $request): array;
    public function transfer(array $data): array;
}
