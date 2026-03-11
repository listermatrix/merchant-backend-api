<?php
namespace Domain\PayMerchant\SaanaPay\Networks;

use App\Models\Wallet;
use Illuminate\Support\Arr;
use Domain\ApiClients\SaanaPay;
use Domain\PayMerchant\Models\Fund;
use Illuminate\Support\Facades\Log;
use App\Http\ControllerTraits\ServiceCharge;
use App\Exceptions\CustomBadRequestException;
use App\Http\ControllerTraits\DisablesService;
use Domain\PayMerchant\Strategies\CardNetwork;
use App\Exceptions\SomethingWentWrongException;          
use Domain\PayMerchant\SaanaPay\Factories\ResponseHandlerFactory;

class SaanaPayCardNetwork extends CardNetwork
{
    use DisablesService, ServiceCharge;

    protected mixed $amountInString;
    protected $computedAmountPlusFeeCharge;
    protected Fund $fund;
    public function canHandlePayload(): bool
    {
        $currencies = collect(['NGN']);

        return $currencies->contains($this->currency);
    }

    /**
     * @throws SomethingWentWrongException
     * @throws CustomBadRequestException
     * @throws \Throwable
     */
    public function handle(Wallet $wallet)
    {
        $this->ensurePaymentGatewayServiceActive("NG_NGN", "fund_state", $wallet);

        $minimumAllowedTransactionAmountByProcessor = 200;

        $this->ensureAmountIsAllowed($this->currency, $this->amount, $minimumAllowedTransactionAmountByProcessor);

        $feeCode = $this->getChannelFeeCode();

        $this->ensureMerchantPaymentChannelSettingConfigured( $wallet->client, $feeCode );

        $this->amount = ceil( $this->amount );

        $this->computedAmountPlusFeeCharge = ceil( $this->customerDebitComputation($wallet->client, $feeCode, $this->amount) );

        $this->transactionId = makeGenericId();

        $this->amountInString = makeStringMoney($this->amount);

        $payload = [
            'transactionId' =>  $this->transactionId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'customerName' => "{$this?->customerFirstname} {$this?->customerLastname}",
            'customerEmail' => $this?->customerEmail,
        ];

        try {

            $createPaymentResponse = $this->createPaymentRequest($payload);

            $chargeResponse = $this->getChargeAmount($this->transactionId);

            $saanaPayAmountChargeable = Arr::get($chargeResponse, 'amount');

            $debitCardResponse = $this->initiateCardDebit($this->transactionId, $saanaPayAmountChargeable);

            $this->fund = Fund::create([
                'channel_id' => $this->paymentMethod->id,
                'remark' => getRemark($this->meta),
                'transaction_method' => 'card',
                'transaction_channel' => 'card',
                'momo_contact' => $this->customerMsisdn,
                'currency' => $this->currency,
                'target_client_id' => $wallet->client->id,
                'source_client_id' => $wallet->client->id,
                'status' => 'pending',
                'wallet_balance' => $wallet->balance,
                'balance_before' => $wallet->balance,
                'status_reason' => 'Request execution ongoing',
                'wallet_id' => $wallet->id,
                'transaction_id' => $this->transactionId,
                'transaction_amount' => $this->amountInString,
                'amount_in_figures' => $this->amount,
                'processor' => 'saanapay',
                'processor_transaction_id' => Arr::get($createPaymentResponse, 'data.invoice_no'),
                'position' => 'credit',
                'card_number' => maskCardNumber($this->cardNumber),
                'meta' => [
                    'customer' => [
                        'name' => "{$this?->customerFirstname} {$this?->customerLastname}",
                        'email' => $this?->customerEmail
                    ],
                    'saanapay' => [
                        'create_payment_response' => $createPaymentResponse,
                        'change-response' => $chargeResponse,
                        'debit_card_response' => $debitCardResponse
                    ],
                    'amount_plus_charge' => $this->computedAmountPlusFeeCharge,
                ],
            ]);


            $responsePayload = $this->getPaymentResponsePayload($debitCardResponse);

            $responseHandler = ResponseHandlerFactory::getHandler($responsePayload);

            logInvoicePayment($this->fund, $this->meta, $this->customerMsisdn);

            return $responseHandler->handle($wallet);

        } catch (SomethingWentWrongException $e) {

            throw new SomethingWentWrongException($e->getMessage());
        }
    }

    public function getPaymentResponsePayload($response): array
    {
        return [
            'provider_response' => $response,
            'status' => 'redirect_authorization_url',
            'provider' => 'saanapay',
            'transaction_id' => $this->transactionId,
        ];
    }
    private function getChannelFeeCode(): string
    {
        return 'BFCS002';
    }

    private function createPaymentRequest($payload): array
    {
        $saanaPay = app(SaanaPay::class);

        $response = $saanaPay->createPaymentRequest($payload);

        if(Arr::get($response,'status') !== true) {
            Log::channel('saanapay')->error('createpayment', ['request' => $payload, 'response' => $response]);
            throw new SomethingWentWrongException('Failed to create payment request');
        }

        return $response;

    }

    private function getChargeAmount($transactionId)
    {
        $saanaPay = app(SaanaPay::class);

        $response = $saanaPay->getChargeAmount($transactionId);

        if(Arr::get($response,'status') !== true) {
          Log::channel('saanapay')->error('get charge', ['requestId' => $transactionId, 'response' => $response]);
          throw new SomethingWentWrongException('Failed to create payment request');
         }

        return $response;
    }
    private function initiateCardDebit(string $transactionId, float $chargeAmount)
    {
        $saanaPay = app(SaanaPay::class);

        $payload = [
            'amount'           => $chargeAmount,
            'cvv'              => $this->cardCvv,
            'request_id'       => $transactionId,
            'card_number'      => $this->cardNumber,
            'card_expiration'  =>  "$this->expiryMonth/$this->expiryYear"
        ];

        $response = $saanaPay->debitCard($payload);

        if(Arr::get($response,'status') !== true) {
            Log::channel('saanapay')->error('card', [
                'payload' => [
                    'transactionId' => $transactionId,
                     'amount' => $chargeAmount
                    ],
                'response' => $response]);
            throw new SomethingWentWrongException('Failed to initiate card debit');
        }

        return $response;
    }

}
