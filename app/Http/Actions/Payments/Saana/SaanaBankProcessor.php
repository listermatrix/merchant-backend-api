<?php

namespace App\Http\Actions\Payments\Saana;

use App\DataTransferObjects\Transaction\ChargeData;
use App\Events\TransactionMailEvent;
use App\Exceptions\WalletBalanceInsufficientException;
use App\Helpers\Transaction;
use App\Http\Actions\Payments\Saana\Trait\HandleRequest;
use App\Http\ControllerTraits\DisablesCashoutAction;
use App\Models\SaanaPayMomoPayoutTransaction;
use App\Models\Wallet;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\DataTransferObject\Exceptions\UnknownProperties;

class SaanaBankProcessor
{
    use HandleRequest, DisablesCashoutAction;
    /**
     * @var Repository|Application|mixed
     */
    private mixed $baseUrl;
    /**
     * @var Repository|Application|mixed
     */
    private mixed $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.saanapay.payout.base_url');
        $this->apiKey = config('services.saanapay.payout.api_token');
    }

    /**
     * @throws \Throwable
     */
    public function process($dto): array
    {
        $trxPassedValidation = false;
        $feeAmount = 0;
        DB::beginTransaction();

        try {
            $wallet = Wallet::whereUuid(request('wallet_id'))->lockForUpdate()->first();

           $this->ensurePaymentGatewayServiceActive('NG_NGN', 'withdraw_state', $wallet);

            $fee = Transaction::fee(
                $dto->amount,
                $dto->cashOutMethod,
                $dto->feeCode
            );

            $feeAmount = $fee->tofloat();

            $totalAmount = $dto->amount + $feeAmount;

            throw_if(
                $wallet->canNotPay($totalAmount),
                new WalletBalanceInsufficientException(message: 'Wallet balance insufficient.')
            );

            safelyDebit($wallet->id, $dto->amount);

            $chargeData = new ChargeData(['remark' => 'charge', 'sms' => ['heading' => 'Charge for cash out.',],]);
            Transaction::charge($wallet, $dto->amount, $dto->feeCode, $chargeData,false); //added false to disable the sms

            $trxPassedValidation = true;

            DB::commit();
        } catch (\Exception $e) {
            $message = $e->getMessage();

            DB::rollBack();
        }
        $accountResolutionPayload = [
            "bank_code" => $dto->cashOutMethod->code,
            "bank" => $dto->cashOutMethod->name,
            "account_number" => $dto->accountNumber,
        ];

        $beneficiaryName  = $this->getAccountDetails($accountResolutionPayload);

        $user = auth()->user();
        $trx = logTransaction([
            'remark' => 'payout',
            'transaction_method' => $dto->cashOutMethod->fund_type,
            'bank_account' => $dto->accountNumber,
            'transaction_channel' => $dto->cashOutMethod->channel,
            'transaction_id' => $dto->transactionId,
            'currency' => $wallet->currency,
            'target_client_id' => $wallet->client->id,
            'source_client_id' => $wallet->client->id,
            'status' => 'pending',
            'processor' => 'saanapay',
            'balance_before' => $wallet->balance,
            'wallet_balance' => $wallet->refresh()->balance,
            'status_reason' => 'Transaction is processing',
            'wallet_id' => $wallet->id,
            'transaction_amount' => makeStringMoney($dto->amount),
            'amount_in_figures' => $dto->amount,
            'app_fee' => $feeAmount,
            'position' => 'debit',
            'author_type' => get_class($user),
            'author_id' => $user->id,
            'meta' => [
                'beneficiary_details' => [
                    'beneficiary_name' => Arr::get($beneficiaryName,'data.account_name'),
                    'account_id' => $dto->accountNumber
                ]
            ]
        ]);

        $dto->trx = $trx;
        $dto->charge = $feeAmount;
        $dto->wallet = $wallet;
        $dto->saanaPayLog = $this->saanaPayLog($dto);

        if ($trxPassedValidation) {
            $response = $this->handle($dto);

            return array_merge($response, [
                'wallet' => $wallet->refresh(),
            ]);
        }

        $trx->status = 'failed';
        $trx->status_reason = $message;
        $trx->wallet_balance = $wallet->refresh()->balance;
        $trx->save();

        return [
            'httpCode' => 422,
            'wallet' => $wallet->refresh(),
            'description' => $message,
            'statusCode' => REQUEST_FAILED_VALIDATION_CODE,
        ];
    }

    /**
     * @throws UnknownProperties
     * @throws \JsonException
     */
    public function handle($dto)
    {
        [$response, $rawResponse] = $this->processRequest($dto);

        Log::channel('saanapay')->info('Ng Cashout Response',[$response]);

        $status = Arr::get($response, 'status');
        $statusDescription = Arr::get($response, 'description');
        $dto->trx->processor_transaction_id = null;

        $response = [
            'status' => $status,
            'response' => $response,
            'statusDescription' => $statusDescription,
            'rawResponse' => $rawResponse,
            'dto' => $dto
        ];

        return ResponseHandlerFactory::getResponseHandler($response)->handle();
    }

    public function saanaPayLog($dto)
    {
        return SaanaPayMomoPayoutTransaction::class::create([
            'destination' => $dto->accountNumber,
            'amount' => request('amount'),
            'wallet_trx_id' => $dto->trx->id,
            'transaction_id' => $dto->transactionId,
            'fee' => $dto->charge,
            'request_payload' => $dto->payload,
            'status' => 'pending',
        ]);
    }


    public function getAccountDetails($payload)
    {
        return  Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
        ])->post("{$this->baseUrl}/resolveaccount", $payload)->json();
    }
}
