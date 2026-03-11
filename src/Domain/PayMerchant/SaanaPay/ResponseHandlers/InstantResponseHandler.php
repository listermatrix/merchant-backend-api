<?php 

namespace Domain\PayMerchant\SaanaPay\ResponseHandlers;


use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Exceptions\CustomModelNotFoundException;
use Domain\PayMerchant\SaanaPay\Strategies\ResponseHandler;

class InstantResponseHandler extends ResponseHandler
{
    public function canHandlePayload(): bool
    {
        return $this->provider === 'saanapay' && $this->status === 'instant';
    }

    public function handle(Wallet $wallet): array
    {
        $fund = WalletTransaction::whereTransactionId($this->transactionId)
                    ->first();

        if (blank($fund)) {
            throw new CustomModelNotFoundException('No fund transaction found.');
        }

        return [
            'status' => 200,
            'httpStatus' => 200,
            'data' => [
                'fund' => $fund,
                'meta' => [
                    'bank_detail' => $this->paymentDetails,
                ],
            ],
            'message' => 'Payment is being processed...',
        ];

    }
}