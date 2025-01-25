<?php

namespace Emmy\Ego\Exception;

use Exception;

class ApiException extends Exception
{
    public function __construct(string $errorMessage = '', ?\Throwable $previous = null, int $code = 0, array $headers = [])
    {
        $message = json_decode($errorMessage, true);
        $newMessage = [
            'status' => false,
            'message' => ($message && is_array($message)) ? $message['message'] : $errorMessage,
            'api_message' => $message ?? $errorMessage,
        ];
        parent::__construct(json_encode($newMessage), 412, $previous);
    }
}