<?php 

namespace Emmy\Ego\Trait;

use Emmy\Ego\Exception\ApiException;
use Emmy\Ego\Exception\InvalidRecipientException;
use Exception;
use Illuminate\Support\Facades\Http as IlluminateHttp;

trait Http
{
    protected $http;
	public function createConnection(bool $asForm=false):void
	{
		if(!$this->http){
			if($asForm){
				$this->http = IlluminateHttp::withToken($this->secretKey)->asForm();
			}
			else{
				$this->http = IlluminateHttp::withToken($this->secretKey);
			}
		}
	}

	public function getCleanPath(string $path): string
	{
		$baseUrl = rtrim($this->baseUrl, '/');
		$path = ltrim($path ?? '', '/');
		return $baseUrl . '/' . $path;
	}

	public function makeRequest(
		string $method,
		string $path='',
		array $data = [],
		array $headers = []
		): array
	{
		$this->createConnection();
		$url = $this->getCleanPath($path);
		// unset($headers['accountId']);
		// dd($url, $method, $data, $headers);
		$response = match (strtoupper($method)) {
                'GET' => $this->http->withHeaders($headers)->get($url, $data),
                'POST' => $this->http->withHeaders($headers)->post($url, $data),
                'PUT' => $this->http->withHeaders($headers)->put($url, $data),
                'DELETE' => $this->http->withHeaders($headers)->delete($url, $data),
                default => throw new Exception("Unsupported HTTP method: {$method}")
            };
			// dd($response->body());
		$response = json_decode($response, true);
		// dd($response);
        $this->checkForError($response);
		
		return $response;
	}

	public function post(
		string $path='', 
		array $data = [], 
		array $headers = []
		): array
	{
        $this->createConnection();
		$response = $this->http->withHeaders($headers)->post($this->getCleanPath($path), $data);
		$response = json_decode($response, true);
        $this->checkForError($response);
		
		return $response;
	}

    public function get(string $path='', array $data = [], array $headers = []): array
	{
        $this->createConnection();
		$response = $this->http->withHeaders($headers)->get($this->getCleanPath($path), $data);
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
        if (isset($response['status']) && $response['status'] != true) {
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