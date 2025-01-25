<?php 

namespace Emmy\Ego\Trait;

use Emmy\Ego\Exception\ApiException;
use Emmy\Ego\Exception\InvalidRecipientException;
use Illuminate\Support\Facades\Http as IlluminateHttp;

trait Http
{
    protected $http;
	public function createConnection():void
	{
		$this->http = IlluminateHttp::withToken($this->secretKey);
	}
	public function post(string $endpoint='', array $data = [], array $headers = []): array
	{
        $this->createConnection();
		$response = $this->http->withHeaders($headers)->post($this->baseUrl . $endpoint, $data);
		$response = json_decode($response, true);
        $this->checkForError($response);
		
		return $response;
	}

    public function get(string $endpoint='', array $data = [], array $headers = []): array
	{
        $this->createConnection();
		$response = $this->http->withHeaders($headers)->get($this->baseUrl . $endpoint, $data);
		$response = json_decode($response, true);
        $this->checkForError($response);
		
		return $response;
	}

	/**
	 * This error checking is unique to Paystack. 
	 * Can be made elaborate to accomodate other gateways or overriden in your own class
	 */ 
    public function checkForError(array $response):void
    {
        if ($response['status'] && $response['status'] != true) {
			$error = json_encode($response);
			switch ($response['code']) {
				case "transaction_not_found":
				case "invalid_params":
				case 'invalid_Key':
					throw new ApiException($error);
				case "email_address_authorization_code_mismatch":
				case 'invalid_transfer_recipient':
					throw new InvalidRecipientException($error);
				default:
					throw new ApiException($error);
			}
		}
    }
}