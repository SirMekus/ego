<?php

namespace Emmy\Ego\Gateway\Flutterwave;

use Emmy\Ego\Trait\Http;
use Illuminate\Http\Request;
use Emmy\Ego\Exception\ApiException;
use Emmy\Ego\Gateway\Realm\Tollgate;
use Emmy\Ego\Interface\PaymentGatewayInterface;
use Emmy\Ego\Exception\InvalidRecipientException;

class Flutterwave extends Tollgate implements PaymentGatewayInterface
{
	use Http;
	protected $secretKey;
	protected $baseUrl = 'https://api.flutterwave.com/v3/';

	public function __construct()
	{
		$this->secretKey ??= config('ego.credentials.flutterwave.secret_key');
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
		$reference = searchArray('tx_ref', $data) ?? searchArray('reference', $data);
		$metadata = searchArray('metadata', $data) ?? searchArray('metaData', $data);
		$callbackUrl = searchArray('redirect_url', $data) ?? searchArray('callback_url', $data) ?? searchArray('callbackUrl', $data);

		$this->setEmail($email);
		$this->setAmount($amount);
		if($metadata){
			$this->setMetadata($metadata);
		}
		if($callbackUrl){
			$this->setRedirectUrl($callbackUrl);
		}
		if($reference){
			$this->setTxRef($reference);
		}
		if($currency){
			$this->setCurrency($currency);
		}

		return $this->builder;
	}

	public function getBanks(string $countryCode="NG"): array
	{
        $supportedCountries = ['NG', 'KE', 'UG', 'GH', 'ZA'];
        if ($countryCode && !in_array($countryCode, $supportedCountries)) {
            throw new ApiException('Country code not supported');
        }
        else{
            $countryCode = "NG";
        }
		$response = $this->get("banks/$countryCode");
		return $response;
	}
	public function verifyAccountNumber(?array $request=[]): array
	{
		$payload = $this->buildPayload($request);
		$response = $this->post('accounts/resolve',$payload);
		return $response;
	}

	public function pay(?array $data = []): array
	{
		$payload = $this->buildPayload($data);
		$this->createConnection();
        $response = $this->post('payments', $payload);
		$url = $response['data']['link'];
		unset($response['status'], $response['message'], $response['data']['link']);
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
		$transferStatus = $this->post('transfers', $payload);
		return $transferStatus;
	}

	public function verifyWebhook(Request $request): void
	{
		$secretHash = config('ego.credentials.flutterwave.secretHash');
        $signature = $request->header('verif-hash');
        if ( ! $signature || ($signature !== $secretHash)) {
            // This request isn't from Flutterwave; discard
            abort(401);
        }
	}

	public function verifyPayment(array|string $payload): array
	{	
		if (is_array($payload)) {
			if (
				isset($payload['data']['trxref']) || 
				isset($payload['data']['id']) || 
				isset($payload['trxref']) || 
				isset($payload['reference']) || 
				isset($payload['ref'])) {
				$paymentReference = $payload['data']['trxref'] ?? 
				$payload['data']['id']??
				$payload['trxref'] ?? 
				$payload['reference'] ?? 
				$payload['ref'];
			}
		}
		else{
			$paymentReference = $payload;
		}
		$header = [
			'Content-Type' => 'application/json',
			'Accept'        => 'application/json'
	    ];

		return is_numeric($paymentReference) ?
			$this->verifyById($paymentReference, $header) :
			$this->verifyByReference($paymentReference, $header);
	}
	public function verifyById(string|int $id, array $header)
	{
		return $this->get("transactions/{$id}/verify", headers:$header);
	}
	public function verifyByReference(string $reference, array $header)
	{
		return $this->get(
			"transactions/verify_by_reference",
			[
				'tx_ref'=>$reference
			],
			$header);
	}

    public function checkForError(array $response):void
    {
        if ($response['status'] && $response['status'] == "error") {
			$error = json_encode($response);
			switch ($response['message']) {
				case 'Invalid authorization key':
					throw new ApiException($error);
                case 'Account resolve failed':
                    throw new InvalidRecipientException($error);
				default:
					throw new ApiException($error);
			}
		}
    }
}
