<?php

namespace Emmy\Ego\Gateway\BudPay;

use Emmy\Ego\Exception\ApiException;
use Emmy\Ego\Gateway\Realm\Tollgate;
use Emmy\Ego\Interface\BankingInterface;
use Emmy\Ego\Interface\PaymentGatewayInterface;
use Emmy\Ego\Trait\Http;
use Emmy\Ego\Trait\Webhooker;
use Illuminate\Http\Request;

class BudPay extends Tollgate implements PaymentGatewayInterface, BankingInterface
{
    use Http, Webhooker;

    protected $secretKey;

    protected $publicKey;

    protected $baseUrl = 'https://api.budpay.com/';

    public function __construct()
    {
        $this->secretKey ??= config('ego.credentials.budpay.secret_key');
        $this->publicKey ??= config('ego.credentials.budpay.public_key');
    }

    /**
     * @inheritDoc
     */
    public function setKey(string|array $key): void
    {
        $this->secretKey = $key;
    }

    public function checkForError(array $response): void
    {
        if (
            (isset($response['status']) && $response['status'] != true) ||
            (isset($response['success']) && $response['success'] != true)
        ) {
            throw new ApiException(json_encode($response));
        }
    }
    /**
     * @inheritDoc`
     */
    public function prepareForPayment(array $data): array
    {
        $email = searchArray('email', $data);
        $amount = searchArray('amount', $data);
        $currency = searchArray('currency', $data);
        $firstName = searchArray("first_name", $data) ?? searchArray("firstName", $data);
        $lastName = searchArray("last_name", $data) ?? searchArray("lastName", $data);
        $reference = searchArray('reference', $data);
        $callbackUrl = searchArray('callback', $data) ?? searchArray('callbackUrl', $data) ?? searchArray('callback_url', $data);

        $this->setEmail($email);
        $this->setAmount($amount);
        if ($currency) {
            $this->setCurrency($currency);
        }
        if ($firstName) {
            $this->setFirstName($firstName);
        }
        if ($lastName) {
            $this->setLastName($lastName);
        }
        if ($reference) {
            $this->setReference($reference);
        }
        $this->setCallback($callbackUrl);

        return $this->builder;
    }

    /**
     * @inheritDoc
     */
    public function pay(array $array): array
    {
        $payload = $this->buildPayload($array);
        $response = $this->post('api/v2/transaction/initialize', $payload);

        return [
            "status" => true,
            "url" => $response["data"]["authorization_url"],
            "message" => "Payment URL created",
            'api_message' => $response,
        ];
    }

    /**
     * @inheritDoc
     *  Fetch all supported banks.
     */
    public function getBanks(string $bankcode = ''): array
    {
        return $this->get("api/v2/bank_list");
    }

    /**
     * Fetch supported banks via their country's currency codes.
     * 
     * Check BudPay docs for supported countries.
     * 
     * @see https://developer.budpay.com/making-payment/bank-list
     * 
     * @param string $currencyCode
     * 
     * @return array
     */
    public function getBanksByCurrency(string $currencyCode = "NGN"): array
    {
        return $this->get("api/v2/bank_list/{$currencyCode}");
    }

    /**
     * @inheritDoc
     * Verify the recipient's account exists.
     */
    public function verifyAccountNumber(array $request): array
    {
        $payload = $this->buildPayload($request);
        return $this->post("api/v2/account_name_verify", $payload);
    }

    /**
     * Calculate budpay transfer fees before making transfers.
     * 
     * @see https://developer.budpay.com/making-payment/payout-fees#fee-calculation-examples for more info.
     * 
     * @param array $data
     * 
     * @return array
     */
    public function calculateTransferFee(array $data): array
    {
        $currency = searchArray("currency", $data);
        $amount = searchArray("amount", $data);

        $this->setCurrency($currency);
        $this->setAmount($amount);

        return $this->post("api/v2/payout_fee", $this->buildPayload($data));
    }

    /**
     * @inheritDoc
     * Prepare for single or bulk BudPay transfers.
     */
    public function prepareForTransfer(array $data): array
    {
        // Bulk transfer is identified by the presence of the 'transfers' key in the request array.
        $transfers = searchArrayAsArray("transfers", $data);
        if ($transfers) {

            $currency = searchArray("currency", $data);
            $transfers = searchArrayAsArray("transfers", $data);

            $this->setCurrency($currency);
            $this->setTransfers($transfers);

            return $this->builder;
        }

        // Single transfer preparation.
        $currency = searchArray("currency", $data);
        $amount = searchArray("amount", $data);
        $accountNumber = searchArray("account_number", $data);
        $bankCode = searchArray("bank_code", $data);
        $narration = searchArray("narration", $data);
        $bankName = searchArray("bank_name", $data);
        $metadata = searchArrayAsArray("meta_data", $data);
        $paymentMode = searchArray("paymentMode", $data) ?? searchArray("payment_mode", $data); // Available for Kenya only at the time of building.

        $this->setCurrency($currency);
        $this->setAmount($amount);
        $this->setBankCode($bankCode);
        $this->setNarration($narration);
        $this->setAccountNumber($accountNumber);
        $this->setBankName($bankName);
        if ($metadata) {
            $this->builder['meta_data'] = $metadata;
        }
        if ($paymentMode) {
            $this->builder['paymentMode'] = $paymentMode;
        }

        return $this->builder;
    }

    /**
     * @inheritDoc
     * 
     */
    public function transfer(array $data): array
    {
        $payload = $this->buildPayload($data);

        if (isset($payload['transfers']) && \is_array($payload['transfers'])) {
            return $this->handleTransfer('api/v2/bulk_bank_transfer', $payload);
        }

        return $this->handleTransfer('api/v2/bank_transfer', $payload);
    }


    /**
     * Handles budpay transfers - both single and bulk.
     * @param string $endpoint
     * @param array $payload
     * @return array
     */
    private function handleTransfer(string $endpoint, array $payload): array
    {
        ksort($payload);

        $hmacSignature = hash_hmac(
            'sha512',
            json_encode($payload),
            $this->publicKey
        );

        return $this->post($endpoint, $payload, [
            'Encryption' => $hmacSignature,
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function verifyPayment(array|string $budPayReference, ?string $paymentType = null): array
    {
        $paymentType = $paymentType !== null ? strtolower($paymentType) : null;
        $reference = null;
        $route = 'transaction';

        if (\is_array($budPayReference)) {
            $reference = $this->resolveBudPayReference($budPayReference);
            $route = $this->getBudPayVerifyRoute($budPayReference, $paymentType);
        } else {
            $reference = $budPayReference;
            if ($paymentType !== null) {
                $route = $this->mapBudPayVerifyRoute($paymentType);
            }
        }

        if (!$reference) {
            throw new \InvalidArgumentException('Payment reference is required');
        }

        return $this->get(
            $route === 'payout'
                ? "api/v2/payout/{$reference}"
                : "api/v2/transaction/verify/{$reference}"
        );
    }

    public function verifyTransaction(string $reference): array
	{	
		return $this->verifyPayment($reference, 'transaction');
	}

    // Resolves the BudPay reference from the given payload.
    private function resolveBudPayReference(array $payload): ?string
    {
        return $payload['data']['reference'] ??
            $payload['reference'] ??
            $payload['transferDetails']['paymentReference'] ??
            null;
    }

    // Determines the appropriate BudPay verification route based on the payload and optional payment type.
    private function getBudPayVerifyRoute(array $payload, ?string $paymentType = null): string
    {
        if ($paymentType !== null) {
            return $this->mapBudPayVerifyRoute($paymentType);
        }

        $event = strtolower((string) ($payload['notify'] ?? $payload['event'] ?? ''));
        if ($event === 'payout') {
            return 'payout';
        }

        if (isset($payload['transferDetails']['paymentReference'])) {
            return 'payout';
        }

        return 'transaction';
    }

    // Maps generic payment types to BudPay-specific verification routes.
    private function mapBudPayVerifyRoute(string $paymentType): string
    {
        return match ($paymentType) {
            'transaction', 'payment' => 'transaction',
            'payout', 'transfer', 'bank_transfer' => 'payout',
            default => throw new \InvalidArgumentException("Invalid Payment type. \n Payment type should be 'transaction' or 'payout'."),
        };
    }

    /**
     * @inheritDoc
     */
    public function verifyWebhook(Request $request): void
    {
        if (!$this->shouldValidateWebhook()) {
            return;
        };

        if (!$request->isMethod('POST')) {
            abort(401);
        }

        $event = strtolower((string) $request->input('notify'));
        $reference = $request->input('data.reference')
            ?? $request->input('transferDetails.paymentReference')
            ?? $request->input('reference');

        if (!$event || !$reference || !\in_array($event, ['transaction', 'virtual_account', 'payout'], true)) {
            abort(401);
        }

        try {
            $verification = $this->verifyPayment(
                $reference,
                $event === 'payout' ? 'payout' : 'transaction'
            );
        } catch (\Throwable) {
            abort(401, "Webhook not verified");
        }

        $verifiedReference = $verification['data']['reference'] ?? $verification['reference'] ?? null;
        if (!$verifiedReference || !hash_equals((string) $reference, (string) $verifiedReference)) {
            abort(401, "Webhook not verified");
        }

        $webhookStatus = $request->input('data.status');
        $verifiedStatus = $verification['data']['status'] ?? $verification['status'] ?? null;
        if ($webhookStatus && $verifiedStatus && !hash_equals((string) $webhookStatus, (string) $verifiedStatus)) {
            abort(401, "Webhook not verified");
        }
    }
}
