<?php

declare(strict_types=1);

namespace Emmy\Ego\Interface;

interface BankingInterface
{
    //Helps to create the appropriate payload when you pass it an array containing the values the target gateway expects. The underlying payment gateway class will determine how many of the 'important' payloads it will set.
    public function prepareForTransfer(array $data): array;

    //To fetch a list of available banks the underlying payment gateway supports
    public function getBanks(string $bankcode=""): array;

    //Verifies an account number
    public function verifyAccountNumber(array $request): array;

    //To run a transfer/withdrawal transaction based on the payment gateway
    public function transfer(array $data): array;

    public function verifyTransaction(string $reference): array;
}
