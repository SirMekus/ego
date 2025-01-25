<?php

namespace Emmy\Ego\Gateway\Paystack;

use Illuminate\Http\Request;
use Emmy\Ego\Exception\ApiException;
use Emmy\Ego\Gateway\Realm\Tollgate;
use Emmy\Ego\Trait\Http;
use Emmy\Ego\Interface\PaymentGatewayInterface;

class Paystack extends Tollgate implements PaymentGatewayInterface
{
	use Http;
	protected $secretKey;
	protected $baseUrl = 'https://api.paystack.co/';

	public function __construct()
	{
		$this->secretKey ??= config('ego.credentials.paystack.secret_key');
	}

	public function setKey(string|array $key):void
	{
		$this->secretKey =  $key;
	}

	public function prepareForPayment(array $data): array
	{
		$email = searchArray('email', $data);
		$amount = searchArray('amount', $data);
		$currency = searchArray('currency', $data);
		$bearer = searchArray('bearer', $data);
		$reference = searchArray('reference', $data);
		$metadata = searchArray('metadata', $data) ?? searchArray('metaData', $data);
		$callbackUrl = searchArray('callback_url', $data) ?? searchArray('callbackUrl', $data);
		$channels = searchArray('channels', $data);

		$this->setEmail($email);
		$this->setAmount($amount);
		if($metadata){
			$this->setMetadata($metadata);
		}
		if($callbackUrl){
			$this->setCallbackUrl($callbackUrl);
		}
		if($channels){
			$this->setChannels($channels);
		}
		if($reference){
			$this->setReference($reference);
		}
		if($currency){
			$this->setCurrency($currency);
		}
		if($bearer){
			$this->setBearer($bearer);
		}

		return $this->builder;
	}

	public function getBanks(string $countryCode=""): array
	{
		$response = $this->get('bank');
		return $response;
	}
	public function verifyAccountNumber(array $request=[]): array
	{
		$payload = $this->buildPayload($request);
		$response = $this->get('bank/resolve',$payload);
		return $response;
	}

	public function pay(?array $data = []): array
	{
		$payload = $this->buildPayload($data);
		$this->createConnection();
		return isset($payload['authorization_code']) ? 
		    $this->payViaAuthorizationCode($payload) :
			$this->payViaAuthorizationUrl($payload);
	}

	protected function payViaAuthorizationUrl(array $data)
	{
		$response = $this->post('transaction/initialize', $data);
		$url = $response['data']['authorization_url'];
		unset($response['status'], $response['message'], $response['data']['authorization_url']);
		return [
			"status" => true,
			"message" => "Authorization URL created",
			'url' => $url,
			'api_message' => $response,
		];
	}

	protected function payViaAuthorizationCode(array $data): array|object
	{
		$response = $this->post('transaction/charge_authorization', $data);
		unset($response['status'], $response['message']);
		return [
			"status" => true,
			"message" => "Charge attempted",
			'api_message' => $response,
		];
	}

	public function createRecipient(array $postdata): array
	{
		$result = $this->post('transferrecipient', $postdata);
		return $result;
	}

	public function transfer(array $data=[]): array
	{
		$payload = $this->buildPayload($data);

		$postData = [
			"source" => searchArray('source', $payload) ?? "balance",
			"amount" => searchArray('amount', $payload),
			"reason" => searchArray('reason', $payload) ?? searchArray('description', $payload),
		];
		if(isset($payload['recipient_code'])){
			$postData['recipient'] = $payload['recipient_code'];
		}
		else{
			$recipient = $this->createRecipient($payload);
			$postData['recipient'] = $recipient['data']['recipient_code'];
		}
		$transferStatus = $this->post('transfer', $postData);
		return $transferStatus;
	}

	public function verifyWebhook(Request $request): void
	{
		$signature = $request->header('x-paystack-signature');
		if (
			(strtoupper($_SERVER['REQUEST_METHOD']) != 'POST') ||
			!$signature
		)
			abort(401);

		// validate event do all at once to avoid timing attack
		if ($signature !== hash_hmac('sha512', $request->getContent(), $this->secretKey))
			abort(401, "Webhook not verified");

		// parse event (which is json string) as object
		// Do something - that will not take long - with $event
		// PaystackEvent::dispatch(json_decode($input));

		exit();
	}

	public function verifyPayment(array|string $paystackData): array
	{	
		if (is_array($paystackData)) {
			if (isset($paystackData['data']['trxref']) || isset($paystackData['trxref'])) {
				$paymentReference = isset($paystackData['data']['trxref']) ?? isset($paystackData['trxref']);
				$status = $this->get("transaction/verify/{$paymentReference}");
			}
		}
		else{
			$status = $this->get("transaction/verify/".$paystackData);
		}
		return $status;
	}

	/**
	 * Takes the webhook payload, verifies that the webhook is legit then return a different payload structure 
	 * gotten via the payment verification endpoint.
	 */
	public function handleWebhook(array $payload): array
	{
		$gatewayData = [];
		if ($payload['event'] === 'charge.success') {
			$gatewayData = $this->verifyPayment($payload['data']['reference']);
			return $gatewayData;
		}
		if ($payload['event'] === 'transfer.success') {
			$gatewayData = $payload;
			return $gatewayData;
		}
		throw new ApiException('Paystack Webhook verification failed. ');
	}
}
