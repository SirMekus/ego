<?php

namespace Emmy\Ego\Gateway\Stripe;

use Emmy\Ego\Trait\Http;
use Illuminate\Http\Request;
use Emmy\Ego\Exception\ApiException;
use Emmy\Ego\Gateway\Realm\Tollgate;
use Emmy\Ego\Gateway\Stripe\Trait\Utils;
use Emmy\Ego\Interface\PaymentGatewayInterface;
use Emmy\Ego\Exception\InvalidRecipientException;

class Stripe extends Tollgate implements PaymentGatewayInterface
{
	use Http, Utils;
	protected $secretKey;
	protected $accountId;
	protected $baseUrl = 'https://api.stripe.com/v1/';

	public function __construct()
	{
		$this->secretKey ??= config('ego.credentials.stripe.secret_key');
		$this->accountId ??= config('ego.credentials.stripe.account_id');
	}

	public function setKey(string|array $key):void
	{
		$this->secretKey =  $key;
	}

	public function prepareForPayment(array $data): array
	{
		$email = searchArray('email', $data) ?? searchArray('customer_email', $data);
		$amount = searchArray('amount', $data);
		$currency = searchArray('currency', $data) ?? 'usd';
		$mode = searchArray('mode', $data) ?? 'payment';
		$reference = searchArray('tx_ref', $data) ?? searchArray('reference', $data);
		$callbackUrl = searchArray('redirect_url', $data) ?? searchArray('callback_url', $data) ?? searchArray('callbackUrl', $data);

		$this->setCustomerEmail($email);
		if($amount){
			$this->setLineItems([
                [
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => ['name' => searchArray('description', $data) ?? 'Account Funding'],
                        'unit_amount' => $amount,
                    ],
                    'quantity' => searchArray('quantity', $data) ?? 1,
                ],
            ]);
		}
		if($callbackUrl){
			$this->setSuccessUrl($callbackUrl);
		}
		if($reference){
			$this->setClientReferenceId($reference);
		}
		$this->setMode($mode);

		return $this->builder;
	}

	public function getBanks(string $countryCode="NG"): array
	{
		return [];
	}
	public function verifyAccountNumber(?array $request=[]): array
	{
		return [];
	}
	public function pay(?array $data = []): array
	{
		$payload = $this->buildPayload($data);
		$this->createConnection(true);
        $response = $this->post('checkout/sessions', $payload);
		$url = $response['url'];
		unset(
			$response['"success_url" '], 
			$response['object'], 
			$response['automatic_tax'], 
			$response['cancel_url'], 
			$response['livemode'], 
			$response['payment_method_types'], 
			$response['payment_status'], 
			$response['setup_intent'], 
			$response['submit_type'], 
			$response['subscription_data'], 
			$response['success_url'], 
			$response['url']
		);
		return [
			"status" => true,
			"message" => "Authorization URL created",
			'url' => $url,
			'api_message' => $response,
		];
	}

	public function transfer(array $data=[]): array
	{
		$payload = $this->buildPayload($data);

		//Now we rebuild the payload ourselves
		$requestPayload = [
			'currency' => searchArray('currency', $payload),
			'destination' => searchArray('destination', $payload) ?? searchArray('destination_id', $payload),
			'amount' => searchArray('amount', $payload),
			'description' => searchArray('description', $payload),
			'metadata' => searchArray('metadata', $payload),
			'source_transaction' => searchArray('source_transaction', $payload),
			'source_type' => searchArray('source_type', $payload),
			'transfer_group' => searchArray('transfer_group', $payload),
		];

		if(!isset($requestPayload['destination'])){
			throw new ApiException("Please provide a 'destination' property in the request which represents the ID of a connected Stripe account");
		}

		$transferStatus = $this->post('transfers', $requestPayload);
		return $transferStatus;
	}

	public function verifyWebhook(Request $request): void
	{
		$header = $request->header('Stripe-Signature');
		if(!$header || !is_string($header)){
			abort(401);
		}
		$timestamp = $this->getTimestamp($header);
		$headerSignature = $this->getSignature($header);
		$payload = $request->getContent();
		$signedPayloadString = (int) $timestamp.".".$payload;
		$mySignature = hash_hmac('sha256', $signedPayloadString, config("ego.credentials.stripe.signing_secret"));
		if(!hash_equals($headerSignature, $mySignature)){
			abort(401);
		}
	}

	public function verifyPayment(array|string $payload): array
	{
		$this->createConnection(true);
		if (is_array($payload)) {
			if (
				isset($payload['session_id']) || 
				isset($payload['data']['trxref']) || 
				isset($payload['data']['id']) || 
				isset($payload['trxref']) || 
				isset($payload['reference']) || 
				isset($payload['ref'])) {
				$paymentReference =  $payload['session_id'] ?? 
					$payload['data']['trxref'] ?? 
					$payload['data']['id']??
					$payload['trxref'] ?? 
					$payload['reference'] ??
					$payload['ref'];
			}
		}
		else{
			$paymentReference = $payload;
		}

		return $this->get("checkout/sessions/{$paymentReference}");
	}
    public function checkForError(array $response):void
    {
        if (isset($response['error'])) {
			$error = json_encode($response['error']);
			switch ($response['error']['type']) {
				case 'invalid_request_error':
					throw new ApiException($error);
                case 'Account resolve failed':
                    throw new InvalidRecipientException($error);
				default:
					throw new ApiException($error);
			}
		}
    }
}
