<?php

namespace Domain\Disbursement\Payout\PayoutAction\Handlers\SaanaPay\ResponseHandlers;

use App\Models\Wallet;
use Illuminate\Support\Arr;
use App\Models\WalletTransaction;
use App\Exceptions\CustomModelNotFoundException;

abstract class ResponseHandler
{
    public string $status;
    public ?array $response;
    public mixed $processor;
    public mixed $rawResponse;
    public mixed $transactionId;
    public ?string $statusDescription;
    public mixed $processorTransactionId;

    public function __construct(array $data)
    {
        $this->status = Arr::get($data, 'status');
        $this->response = Arr::get($data, 'response');
        $this->processor = Arr::get($data,'processor');
        $this->rawResponse = Arr::get($data,'rawResponse');
        $this->transactionId = Arr::get($data, 'transactionId');
        $this->statusDescription = Arr::get($data,'statusDescription');
        $this->processorTransactionId = Arr::get($data, 'processorTransactionId');
    }

    abstract public function canHandlePayload(): bool;

    abstract public function handle(Wallet $wallet);

     public function getWalletTransaction()
    {
        $transaction = WalletTransaction::whereTransactionId($this->transactionId)->lockForUpdate()->first();

        throw_if(
            blank($transaction),
            new CustomModelNotFoundException('No wallet transaction found')
        );
        return $transaction;
    }
}
