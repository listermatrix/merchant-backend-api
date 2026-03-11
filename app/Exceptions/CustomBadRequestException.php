<?php

namespace App\Exceptions;

use Exception;

class CustomBadRequestException extends Exception
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
            'status' => $this->status ? $this->status : BAD_REQUEST_CODE,
            'data' => $this->data,
            'message' => $this->message,
        ], 400);
    }

    public function getData()
    {
        return $this->data;
    }
}
