<?php

namespace App\Exceptions;

use Exception;

class WalletBalanceInsufficientException extends Exception
{
    public function __construct($status = null, $httpStatus = null, $message = null)
    {
        $this->status = $status;
        $this->httpStatus = $httpStatus;
        $this->message = $message;
    }

    public function render()
    {
        return response()->json([
            'status' => $this->status ?? INSUFFICIENT_WALLET_BALANCE_CODE,
            'data' => null,
            'message' => $this->message ?? 'The sending wallet does not have enough balance to complete the transaction.',
        ], $this->httpStatus ?? 400);
    }
}
