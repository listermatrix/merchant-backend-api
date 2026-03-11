<?php

namespace App\Exceptions;

use Exception;

class TransactionTokenNotFoundInTheHeaderException extends Exception
{
    public function render()
    {
        return response()->json([
            'status' => NO_TRANSACTION_TOKEN_FOUND_IN_HEADER_CODE,
            'data' => null,
            'message' => 'No Transaction Token found in header.',
        ], 404);
    }
}
