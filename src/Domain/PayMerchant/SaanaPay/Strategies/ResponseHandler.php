<?php

namespace Domain\PayMerchant\SaanaPay\Strategies;

use App\Models\Wallet;
use Illuminate\Support\Arr;
use App\Models\WalletTransaction;
use App\Exceptions\CustomModelNotFoundException;

abstract class ResponseHandler
{
    protected mixed $status;
    protected mixed $amount;
    protected mixed $transactionId;
    protected mixed $paymentDetails;
    protected mixed $providerResponse;
    protected mixed $provider = 'saanapay';
    protected mixed $remark = 'paymentlink';
    protected mixed $transactionMethod = 'directbanktransfer';

    protected mixed $paymentAuthUrl;

    public function __construct(array $payload)
    {
        $this->status = Arr::get($payload, 'status');

        $this->amount = Arr::get($payload, 'amount');

        $this->provider = Arr::get($payload, 'provider');
        
        $this->paymentDetails = Arr::get($payload, 'data');
        
        $this->transactionId = Arr::get($payload, 'transaction_id');
       
        $this->providerResponse = Arr::get($payload, 'provider_response');

        $this->paymentAuthUrl = Arr::get($payload, 'provider_response.url');
    }

    abstract public function canHandlePayload(): bool;

    abstract public function handle(Wallet $wallet): array;

    public function getWalletTransaction()
    {
        $transaction = WalletTransaction::whereTransactionId($this->transactionId)->first();

        throw_if(
            blank($transaction),
            new CustomModelNotFoundException('No wallet transaction found')
        );

        return $transaction;
    }
}
