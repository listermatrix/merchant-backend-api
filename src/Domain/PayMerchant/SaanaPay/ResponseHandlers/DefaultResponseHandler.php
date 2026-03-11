<?php

namespace Domain\PayMerchant\SaanaPay\ResponseHandlers;

use Illuminate\Support\Facades\Log;
use App\Http\Resources\WalletResource;
use Domain\PayMerchant\SaanaPay\Strategies\ResponseHandler;

class DefaultResponseHandler extends ResponseHandler
{
    public function canHandlePayload(): bool
    {
        return true;
    }

    public function handle($wallet): array
    {
        Log::info('Processed: '.'but no response handler has been registered yet. - '.$this->transactionId);

        return [
            'status' => 161,
            'httpStatus' => 400,
            'data' => [
                'wallet' => new WalletResource($wallet),
            ],
            'message' => 'Payment could not be completed at this time, Please try again',
        ];
    }
}
