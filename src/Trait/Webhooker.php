<?php 

namespace Emmy\Ego\Trait;

trait Webhooker
{
    protected $http;
	public function checkIfValidationIsNecessary():void
	{
		if( !config('ego.verify_webhook', true) ){
			return;
		}
	}
}