<?php

namespace Emmy\Ego\Gateway\Fincra;

use Emmy\Ego\Trait\Http;
use Illuminate\Http\Request;
use Emmy\Ego\Exception\ApiException;
use Emmy\Ego\Gateway\Realm\Tollgate;
use Emmy\Ego\Interface\PaymentGatewayInterface;
use Emmy\Ego\Exception\InvalidRecipientException;
use Illuminate\Support\Facades\Http as IlluminateHttp;

class Fincra extends Tollgate implements PaymentGatewayInterface
{
	use Http;
	protected $secretKey;
	protected $publicKey;
	protected $businessId;
	protected $baseUrl;

	public function __construct()
	{
		$this->secretKey = config('ego.credentials.fincra.secret_key');
        $this->publicKey = config('ego.credentials.fincra.public_key');
        $this->businessId = config('ego.credentials.fincra.business_id');

        $this->setUrl();
	}
    
	public function setKey(string|array $key):void
	{
        if(!is_array($key)){
            $this->secretKey =  $key;
        }
        else{
            $this->secretKey = $key['secret_key'] ?? null;
            $this->publicKey = $key['public_key'] ?? null;
            $this->businessId = $key['business_id'] ?? null;
        }
	}

    protected function setUrl()
    {
        $this->baseUrl = config('ego.credentials.fincra.production') ?
            "https://api.fincra.com/" :
            "https://sandboxapi.fincra.com/";
    }

    public function createConnection():void
	{
        $this->http = IlluminateHttp::withheaders([
            'accept' => 'application/json',
            'api-key' => $this->secretKey,
            'x-pub-key'=> $this->publicKey,
            'x-business-id'=> $this->businessId,
            'content-type' => 'application/json',
        ]);
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
            $this->builder['redirectUrl'] = $callbackUrl;
		}
		if($reference){
			$this->setReference($reference);
		}
		if($currency){
			$this->setCurrency($currency);
		}

		return $this->builder;
	}

	public function getBanks(string $countryCode="NG"): array
	{
		if(empty($countryCode)){
			$countryCode = "NG";
		}
		$structure = [
			'NG' =>[
				'currency' => 'NGN',
				'countryCode' => 'NG',
			],
			'KE' =>[
				'currency' => 'KES',
				'countryCode' => 'KES',
			],
			'UG' =>[
				'currency' => 'UGX',
				'countryCode' => 'UG',
			],
			'GH' =>[
				'currency' => 'GHS',
				'countryCode' => 'GH',
			],
			'SA' =>[
				'currency' => 'ZAR',
				'countryCode' => 'ZA',
            ],
		];
        if ($countryCode && !in_array($countryCode, array_keys($structure))) {
            throw new ApiException('Country code not supported');
        }
		$response = $this->get("core/banks", [
			'currency'=>$structure[$countryCode]['currency'],
			'country'=>$structure[$countryCode]['countryCode']
		]);
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
        $response = $this->post('checkout/payments', $payload);
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
		$transferStatus = $this->post('disbursements/payouts', $payload);
		return $transferStatus;
	}

	public function verifyWebhook(Request $request): void
	{
		$secretHash = config('ego.credentials.fincra.secretHash');
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
        if (isset($response['error'])) {
			$error = json_encode($response);
			switch ($response['error']) {
				case 400:
					throw new ApiException($error);
                // case 'Account resolve failed':
                //     throw new InvalidRecipientException($error);
				default:
					throw new ApiException($error);
			}
		}
    }
}
