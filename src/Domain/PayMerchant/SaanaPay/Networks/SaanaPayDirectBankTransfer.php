<?php

namespace Domain\PayMerchant\SaanaPay\Networks;

use App\Models\Wallet;
use Illuminate\Support\Arr;
use App\Models\CashInMethod;
use Domain\ApiClients\SaanaPay;
use Domain\PayMerchant\Models\Fund;
use Illuminate\Support\Facades\Log;
use App\Http\ControllerTraits\ServiceCharge;
use App\Http\ControllerTraits\DisablesService;
use App\Exceptions\SomethingWentWrongException;
use App\Models\DirectBankTransferTemporaryAccount;
use Domain\PayMerchant\Strategies\BankTransferNetwork;
use Domain\PayMerchant\SaanaPay\Factories\ResponseHandlerFactory;

class SaanaPayDirectBankTransfer extends BankTransferNetwork
{
    use DisablesService, ServiceCharge;

    protected string $transactionId;
    protected string $amountString;
    protected float $computedAmountPlusFeeCharge;
    protected Fund $fund;
    protected CashInMethod $paymentMethod;

    public function canHandlePayload(): bool
    {
        return $this->network === 'directbanktransfer' && $this->currency === 'NGN';
    }

    public function handle(Wallet $wallet)
    {
            $this->ensurePaymentGatewayServiceActive("NG_NGN", "fund_state", $wallet);

            $minimumAllowedTransactionAmountByProcessor = 200;

            $this->paymentMethod = CashInMethod::where('channel', 'directbanktransfer')->first();

            $this->ensureAmountIsAllowed($this->currency, $this->amount, $minimumAllowedTransactionAmountByProcessor);

            $feeCode = $this->getChannelFeeCode();

            $this->amount = ceil( $this->amount );

            $this->ensureMerchantPaymentChannelSettingConfigured( $wallet->client, $feeCode );

            $this->computedAmountPlusFeeCharge = ceil( $this->customerDebitComputation($wallet->client, $feeCode, $this->amount) );

            $this->amountString  = makeStringMoney($this->amount);

            $this->transactionId = makeGenericId();

            try {

            $payload = [
                'amount' => $this->computedAmountPlusFeeCharge,
                'currency' => $this->currency,
                'transactionId' => $this->transactionId,
                'customerName' => "{$this?->customerFirstname} {$this?->customerLastname}",
                'customerEmail' => $this?->customerEmail,
            ];

            $createPaymentResponse = $this->createPaymentRequest($payload);

            $chargeResponse = $this->getChargeAmount();

            $saanaPayAmountChargeable = Arr::get($chargeResponse, 'amount');

            $bankTransferResponse = $this->initiateDirectBankTransfer($saanaPayAmountChargeable);

            $this->fund = Fund::create([
               'channel_id' => $this->paymentMethod->id,
                'bank_account' => Arr::get($bankTransferResponse, 'account_number'),
                'remark' => getRemark($this->meta),
                'transaction_method' => 'directbanktransfer',
                'momo_contact' => $this->momoNumber,
                'transaction_channel' => 'directbanktransfer',
                'currency' => 'NGN',
                'target_client_id' => $wallet->client->id,
                'source_client_id' => $wallet->client->id,
                'status' => 'pending',
                'wallet_balance' => $wallet->balance,
                'balance_before' => $wallet->balance,
                'status_reason' => 'Request execution ongoing',
                'wallet_id' => $wallet->id,
                'transaction_id' => $this->transactionId,
                'transaction_amount' => $this->amountString,
                'amount_in_figures' => $this->amount,
                'position' => 'credit',
                'processor' => 'saanapay',
                'processor_transaction_id' => Arr::get($createPaymentResponse, 'data.invoice_no'),
                'meta' => [
                    'customer' => [
                        'name' => "{$this?->customerFirstname} {$this?->customerLastname}",
                        'email' => $this?->customerEmail
                    ],
                    'saanapay' => [
                        'create_payment_response' => $createPaymentResponse,
                        'change_response' => $chargeResponse,
                        'bank_transfer_response' => $bankTransferResponse
                    ],
                    'amount_plus_charge' => $this->computedAmountPlusFeeCharge,
                ]
            ]);

            $responsePayload = $this->getPaymentResponsePayload(
                $bankTransferResponse,
                 $saanaPayAmountChargeable,
                 $wallet->uuid
            );

            DirectBankTransferTemporaryAccount::create([
                'client_id' => $wallet->client->id,
                'wallet_id' => $wallet->id,
                'transaction_id' => $this->transactionId,
                'service_id' => strtoupper( getRemark($this->meta) ),
                'wallet_transaction_id' => $this->fund->id,
                'account_number' => Arr::get($bankTransferResponse, 'account_number'),
                'bank_name' => Arr::get($bankTransferResponse, 'bank_name'),
                'processor' => 'SaanaPAY',
                'expired_at' => Arr::get($bankTransferResponse, 'expires_at'),
            ]);

            $responseHandler = ResponseHandlerFactory::getHandler($responsePayload);

            return $responseHandler->handle($wallet);

        } catch (SomethingWentWrongException $e) {

            throw new SomethingWentWrongException($e->getMessage());
        }

    }

    private function getChannelFeeCode(): string
    {
        return 'BFCS001';
    }

    private function createPaymentRequest($payload)
    {
        $saanaPay = app(SaanaPay::class);

        $response = $saanaPay->createPaymentRequest($payload);

        if(Arr::get($response,'status') !== true) {
            Log::channel('saanapay')->error('createpayment', ['request' => $payload, 'response' => $response]);
            throw new SomethingWentWrongException('Failed to create payment request');
        }

        return $response;

    }

    private function getChargeAmount()
    {
        $saanaPay = app(SaanaPay::class);

        $response = $saanaPay->getChargeAmount($this->transactionId);

        if(Arr::get($response,'status') !== true) {
          Log::channel('saanapay')->error('get charge', ['requestId' => $this->transactionId, 'response' => $response]);
          throw new SomethingWentWrongException('Failed to create payment request');
         }

        return $response;
    }
    private function initiateDirectBankTransfer(float $chargeAmount)
    {
        $saanaPay = app(SaanaPay::class);

        $response = $saanaPay->makeDirectBankTransfer($this->transactionId, $chargeAmount);

        if(Arr::get($response,'status') !== true) {
            Log::channel('saanapay')->error('bank transfer', [
                'payload' => [
                    'transactionId' => $this->transactionId,
                     'amount' => $chargeAmount
                    ],
                'response' => $response]);
            throw new SomethingWentWrongException('Failed to initiate bank transfer');
        }

        return $response;
    }

    private function getMessage(): string
    {
        return 'Proceed to your banking application to send payment to the account details provided';
    }

    private function getPaymentResponsePayload(array $paymentResponse, float $amountPayable, string $walletId ): array
    {
            $payload = [
                'currency' => $this->currency,
                'amount' => $amountPayable,
                'bank_account_number' => Arr::get($paymentResponse, 'account_number'),
                'bank_name'   => Arr::get($paymentResponse, 'bank_name'),
                'beneficiary_name' => getBrijUserAccountName($walletId),
                'transaction_id' => $this->transactionId,
                'expires_at' => Arr::get($paymentResponse, 'expires_at'),
                'message' => $this->getMessage()
            ];

            return [
                'data' => $payload,
                'status' => 'instant',
                'provider' => 'saanapay',
                'transaction_id' => $this->transactionId,
            ];
    }

}
