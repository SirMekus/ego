<?php 
namespace Emmy\Ego\Gateway\Realm;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Emmy\Ego\Exception\ConnectionException;

class Tollgate
{	protected $secretKey;
	public $builder = [];
    protected $http;

	public function setKey(string|array $key):void
	{
		$this->secretKey =  $key;
	}

	public function createConnection():void
	{
		$this->http = Http::withToken($this->secretKey);
	}

	public function __call($name, $arguments)
	{
		if (str_starts_with($name, 'set')) {
			$property = substr($name, 3);

			$property = Str::snake($property);

			$this->builder[$property] = $arguments[0];
		} else {
			throw new \RuntimeException(sprintf('Missing %s method.'));
		}
	}

	public function buildPayload(array|string $data=[]):array
	{
		return array_merge($this->builder, $data);
	}
}