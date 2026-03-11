<?php

namespace App\Exceptions;

use Exception;

class TransactionLimitException extends Exception
{
    public function __construct($message)
    {
        $this->message = $message;
    }

    public function render()
    {
        return response()->json([
            'status' => UNEXPLAINED_ERROR_CODE,
            'data' => null,
            'message' => $this->message,
        ], 400);
    }
}
