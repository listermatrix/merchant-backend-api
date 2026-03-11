<?php

namespace App\Exceptions;

use Exception;

class CustomModelNotFoundException extends Exception
{
    public function __construct($message)
    {
        $this->message = $message;
    }

    public function render()
    {
        return response()->json([
            'status' => MODEL_NOT_FOUND_CODE,
            'errors' => [
                'record' => $this->message,
            ],
            'message' => 'Record does not exist with id specified.',
        ], 404);
    }
}
