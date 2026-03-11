<?php

namespace App\Exceptions;

use Exception;

class TransactionTokenHasExpiredException extends Exception
{
    public function render()
    {
        return response()->json([
            'status' => TRANSACTION_TOKEN_HAS_EXPIRED_CODE,
            'data' => null,
            'message' => 'The transaction token passed in the header has expired.',
        ], 404);
    }
}
