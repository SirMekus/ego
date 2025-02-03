<?php 

namespace Emmy\Ego\Gateway\Stripe\Trait;

trait Utils{
    public function getTimestamp(string $header): int
	{
		$splitHeaders = explode(',', $header);
		foreach ($splitHeaders as $key => $value) {
			$pair = explode('=', $value);
			if($pair[0] === 't'){
                return (int) $pair[1];
            }
		}
		return 0;
	}

	public function getSignature(string $header, string $scheme='v1'): string
	{
		$splitHeaders = explode(',', $header);
		foreach ($splitHeaders as $key => $value) {
			$pair = explode('=', $value);
			if($pair[0] === $scheme){
                return $pair[1];
            }
		}
		return '';
	}

	public function createCustomer(array $data=[])
	{
		$payload = $this->buildPayload($data);
		$this->createConnection(true);
		$accountStatus = $this->post("customers", $payload);
		return $accountStatus;
	}

	public function createBankAccount(array $data=[])
	{
		$payload = $this->buildPayload($data);
		$this->createConnection(true);
		$accountStatus = $this->post("accounts/{$this->accountId}/external_accounts", $payload);
		return $accountStatus;
	}
}