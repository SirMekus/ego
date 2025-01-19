<?php

namespace Emmy\Ego\Exception;

use Exception;
// use Illuminate\Http\Client\ConnectionException as CE;

class InvalidRecipientException extends Exception
{
    public function __construct(string $message = '', ?\Throwable $previous = null, int $code = 0, array $headers = [])
    {
        $message = json_decode($message, true);
        $newMessage = [
            'status' => false,
            'message' => $message['message'],
            'api_message' => $message,
        ];
        // dd(json_encode($newMessage));
        parent::__construct(json_encode($newMessage), 412, $previous);
    }
}