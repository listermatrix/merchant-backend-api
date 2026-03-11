<?php

namespace Domain\PayMerchant\SaanaPay\ResponseHandlers;

use App\Models\Wallet;
use App\Http\Resources\WalletResource;
use Domain\PayMerchant\SaanaPay\Strategies\ResponseHandler;

class CardAuthorizationURLHandler extends ResponseHandler
{
    public function canHandlePayload(): bool
    {
       return $this->status === 'open_url' && $this->provider === 'saanapay';
    }

    public function handle(Wallet $wallet): array
    {
        $transaction = $this->getWalletTransaction();
        $meta = array_merge($transaction->meta ?? [], $this->providerResponse);

        $transaction->update([
            'meta' => $meta,
            'status_reason' => 'Awaiting Authorization',
        ]);

        $transaction->authorization_url = $this->paymentAuthUrl;

        return [
            'status' => 200,
            'httpStatus' => 200,
            'data' => [
                'fund' => $transaction,
                'meta' => [
                    'auth_url' => $this->paymentAuthUrl,
                ],
                'wallet' => new WalletResource($wallet),
            ],
            'message' => 'Initiate authorization for payment',
        ];
    }
}