<?php

namespace App\Exceptions;

use Exception;

class KycFailedException extends Exception
{
    //
    public function __construct($message)
    {
        $this->message = $message;
    }

    public function render()
    {
        return response()->json([
            'status' => KYC_ID_VERIFICATION_FAILED_CODE,
            'data' => null,
            'message' => $this->message,
        ], 422);
    }
}
