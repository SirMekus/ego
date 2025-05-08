<?php 
namespace Emmy\Ego\Gateway\Realm;

use Illuminate\Support\Str;

class Tollgate
{	
	protected $builder = [];

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

	public function getPayload():array
	{
		return $this->builder;
	}
}