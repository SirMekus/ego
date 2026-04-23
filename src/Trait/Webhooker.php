<?php 

namespace Emmy\Ego\Trait;

trait Webhooker
{
	public function shouldValidateWebhook():bool
	{
		if(config('ego.verify_webhook') ){
			return true;
		}
		return false;
	}
}