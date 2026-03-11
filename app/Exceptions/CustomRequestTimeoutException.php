<?php

namespace App\Exceptions;

use Exception;

class CustomRequestTimeoutException extends Exception
{
    public function __construct($message, $data = null, $status = null)
    {
        $this->message = $message;
        $this->data = $data;
        $this->status = $status;
    }

    public function render()
    {
        return response()->json([
            'status' => $this->status ? $this->status : TIMEOUT_CODE,
            'data' => $this->data,
            'message' => $this->message ?? 'External Request Timed Out',
        ], 400);
    }
}
