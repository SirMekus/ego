<?php 

namespace Emmy\Ego\Gateway\Paystack\Trait;

use Emmy\Ego\Exception\ApiException;
use Emmy\Ego\Exception\InvalidRecipientException;

trait Http
{
	public function post(string $endpoint, array $data = [], array $headers = []): array
	{
        $this->createConnection();
		$response = $this->http->post($this->baseUrl . $endpoint, $data);
		$response = json_decode($response, true);
        $this->checkForError($response);
		
		return $response;
	}

    public function get(string $endpoint, array $data = [], array $headers = []): array
	{
        $this->createConnection();
		$response = $this->http->get($this->baseUrl . $endpoint, $data);
		$response = json_decode($response, true);
        $this->checkForError($response);
		
		return $response;
	}

    public function checkForError($response):void
    {
        if ($response['status'] != true) {
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