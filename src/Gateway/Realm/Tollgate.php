<?php 
namespace Emmy\Ego\Gateway\Realm;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Emmy\Ego\Exception\ConnectionException;
use Illuminate\Http\Client\ConnectionException as IlluminateException;

class Tollgate
{
	// use ChecksIfWebhookRequestIsLocal;
	protected $secretKey;
	public $builder = [];
    protected $http;

	public function setKey(string $key):void
	{
		$this->secretKey =  $key;
	}

	public function createConnection():void
	{
		try {
            $this->http = Http::withToken($this->secretKey);
        } catch(IlluminateException $e) {
			throw new ConnectionException($e->getMessage());
        }
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
}