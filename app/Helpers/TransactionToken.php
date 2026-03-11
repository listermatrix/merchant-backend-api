<?php

namespace App\Helpers;

use App\Exceptions\CustomModelNotFoundException;
use App\Exceptions\TransactionTokenHasExpiredException;
use App\Exceptions\TransactionTokenNotFoundInTheHeaderException;
use App\Models\TransactionToken as TransactionTokenModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TransactionToken
{
    /**
     * @throws TransactionTokenHasExpiredException
     * @throws CustomModelNotFoundException
     * @throws TransactionTokenNotFoundInTheHeaderException
     */
    public static function isAlive(): bool
    {
        if (self::exemptConsumerApps()) {
            return true;
        }

        $header_transaction_id = request()->header('Transaction-Token');

        if (blank($header_transaction_id)) {
            throw new TransactionTokenNotFoundInTheHeaderException("Invalid Token");
        }

        $client = Auth::user()->client;

        $storedToken = TransactionTokenModel::where('client_id', $client->id)->first();

        if (blank($storedToken)) {
            throw new CustomModelNotFoundException('No Transaction Token found to match the token in the header');
        }

        if ($storedToken->token == $header_transaction_id) {
            if (self::hasExpired($storedToken)) {
                throw new TransactionTokenHasExpiredException;
            }
        } else {
            throw new CustomModelNotFoundException('No Transaction Token found to match the token in the header');
        }

        return true;
    }

    private static function exemptConsumerApps(): ?bool
    {
        $exempted = explode('|', config('brij.transaction_token_exempted_apps'));
        $app = request()->header('CLIENT-TYPE');

        if (in_array($app, $exempted)) {
            return true;
        }
    }

    private static function hasExpired($token): bool
    {
        $expires_at = Carbon::createFromFormat('Y-m-d H:i:s', $token->expires_at);
        $now = Carbon::now();

        if ($now->greaterThan($expires_at)) {
            return true;
        }

        return false;
    }
}
