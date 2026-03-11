<?php

namespace Domain\Disbursement\Payout\PayoutAction\Handlers\SaanaPay\Networks;

use Exception;
use App\Types\Money;
use App\Models\Wallet;
use Illuminate\Support\Arr;
use App\Helpers\Transaction;
use Domain\ApiClients\SaanaPay;
use Illuminate\Support\Facades\DB;
use App\Models\ChargeConfiguration;
use App\Http\ControllerTraits\ServiceCharge;
use App\Exceptions\SomethingWentWrongException;
use App\Http\ControllerTraits\DisablesCashoutAction;
use App\Exceptions\WalletBalanceInsufficientException;
use App\Http\ControllerTraits\ExternalPaymentProcessorInfo;
use Domain\Disbursement\Payout\PayoutAction\Handlers\SaanaPay\Strategies\NetworkStrategy;
use Domain\Disbursement\Payout\PayoutAction\Handlers\SaanaPay\ResponseHandlers\ResponseHandlerFactory;

class BankTransfer extends NetworkStrategy
{
    use DisablesCashoutAction, ServiceCharge, ExternalPaymentProcessorInfo;

    protected string $transactionId;
    protected string $cashOutMethodChargeCode;
    protected string $processorPaymentChannelCode;

    public function canHandlePayload(): bool
    {
        return strtolower($this->cashOutMethod->fund_type) === 'ngbank' &&
                                          $this->cashOutMethod->processor === 'saanapay';
    }

    public function handle(Wallet $wallet): array
    {
        $paymentChannelInfo = $this->getPaymentChannelInfo('SaanaPAY', $this->cashOutMethod->id);
        $this->cashOutMethodChargeCode = $paymentChannelInfo->charge_code;
        $this->processorPaymentChannelCode = $paymentChannelInfo->code;

        $wallet = Wallet::whereUuid($wallet->uuid)->lockForUpdate()->firstOrFail();
        $walletInitialBalance = $wallet->balance;

        $this->validatePrerequisites($wallet);

        $feeAmount = Transaction::fee($this->amount, $this->cashOutMethod, $this->cashOutMethodChargeCode)->toFloat();
           
        $totalAmount = $this->amount + $feeAmount;

        $this->retrieveChargeConfiguration( 
                $this->cashOutMethodChargeCode,
                new Money($this->amount),
        );
            
        if ($wallet->canNotPay($totalAmount)) {
            throw new WalletBalanceInsufficientException(
                INSUFFICIENT_WALLET_BALANCE_CODE,
                400,
                'Transaction failed: available balance insufficient');
        }

        DB::beginTransaction();

        try {

            safelyDebit($wallet->id, $this->amount);

            $this->processTransactionCharge($wallet, $this->cashOutMethodChargeCode, $this->amount);

            $this->transactionId = makePayoutTransactionId();

            $this->logTransaction($wallet, $feeAmount, $walletInitialBalance);

            DB::commit();

        } catch (Exception $exception) {
             DB::rollBack();

            // Log the error message for debugging
            logger()->channel('saanapay')->error('Payout initiation: ', ['error' => exceptionLogger( $exception) ]);

            throw new SomethingWentWrongException('Payment initiation failed.');
        }

        return $this->processDisbursement($wallet);

    }

    private function buildPayload(Wallet $wallet): array
    {
        return [
            'bank_code' => $this->processorPaymentChannelCode,
            'amount' => $this->amount,
            'request_id'=> $this->transactionId,
            'bank'=> $this->cashOutMethod->name,
            'account_number'=> $this->accountId,
        ];
    }

    private function getResponse(array $serverResponse = []): array
    {
        [$response, $rawResponse] = $serverResponse;

        return [
            'status' => Arr::get($response, 'status'),
            'response' => $response,
            'statusDescription' => Arr::get($response, 'description'),
            'rawResponse' => $rawResponse,
            'data' => $serverResponse,
            'processor' => 'saanapay',
            'transactionId' => $this->transactionId,
        ];
    }

    protected function processDisbursement(Wallet $wallet): array
    {
        $payload = $this->buildPayload($wallet);

        $saanapay = app(SaanaPay::class);

        $serverResponse = [];

        try {
            $serverResponse = $saanapay->initiatePayout($payload);
        } catch (Exception $exception) {
             logger()->channel('saanapay')->error('Bank Disbursement failed', [
                    'error' => exceptionLogger($exception)
                ]);
        }

        $response = $this->getResponse($serverResponse);

        return ResponseHandlerFactory::getResponseHandler($response)->handle($wallet);
    }

    protected function validatePrerequisites(Wallet $wallet): void
    {
        $minimumAmount = 100;

        $this->ensurePaymentGatewayServiceActive('NG_NGN', 'withdraw_state', $wallet);
        $this->ensureAmountIsAllowed('NGN', $this->amount, $minimumAmount);
    }

    protected function logTransaction(Wallet $wallet, float $feeAmount, float $walletInitialBalance): mixed
    {
        $feeData = $this->getFeeData();

        return logTransaction([
            'remark' => 'payout',
            'channel_id' => $this->cashOutMethod->id,
            'channel_type' => get_class($this->cashOutMethod),
            'transaction_method' => $this->cashOutMethod->fund_type,
            'bank_account' => $this->accountId,
            'momo_contact' => auth()->user()->phone,
            'transaction_channel' => $this->cashOutMethod->channel,
            'transaction_id' =>  $this->transactionId,
            'currency' => 'NGN',
            'target_client_id' => $wallet->client->id,
            'source_client_id' => $wallet->client->id,
            'status' => 'pending',
            'balance_before' => $walletInitialBalance,
            'wallet_balance' => $wallet->refresh()->balance,
            'status_reason' => 'Transaction is processing',
            'wallet_id' => $wallet->id,
            'transaction_amount' => makeStringMoney($this->amount),
            'amount_in_figures' => $this->amount,
            'app_fee' =>  $feeAmount,
            'position' => 'debit',
            'author_type' => get_class(auth()->user()),
            'author_id' => auth()->user()->id,
            'processor' => 'saanapay',
            'fee_bearer' => 'merchant',
            'description' => $this?->description,
            'fee_value' => $feeData->amount,
            'fee_type' => $feeData->charge_type,
            'fee_code_applied' => $feeData->code,
            'brij_marked_up_rate' => $feeData->brij_marked_up_rate,
            'service_provider_rate' => $feeData->service_provider_rate,
            'meta' => [
                'beneficiary_details' => [
                    'beneficiary_name' => $this->payeeName,
                    'account_id' => $this->accountId
                ]
            ]
        ]);
    }

    private function getFeeData(): ?ChargeConfiguration
    {
        return ChargeConfiguration::where('code', $this->cashOutMethodChargeCode)->first();
    }

}
