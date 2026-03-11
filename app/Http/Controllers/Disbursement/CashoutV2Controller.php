<?php

namespace App\Http\Controllers\Disbursement;

use Hashids\Hashids;
use App\Models\Wallet;
use App\Models\Country;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\CashOutMethod;
use App\Models\PaymentReceipt;
use App\Helpers\TransactionToken;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use App\Http\Actions\Helpers\Guard;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Exceptions\KycFailedException;
use App\Http\Actions\Helpers\Blockers;
use App\Http\Resources\WalletResource;
use App\Repositories\WalletRepository;
use App\Exceptions\CustomBadRequestException;
use App\Exceptions\TransactionLimitException;
use App\Http\Resources\CashOutMethodResource;
use App\Repositories\CashOutMethodRepository;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use App\Exceptions\SomethingWentWrongException;
use App\Exceptions\CustomModelNotFoundException;
use App\Http\ControllerTraits\WalletFund\VerifiesOtp;
use App\Exceptions\WalletBalanceInsufficientException;
use App\Exceptions\TransactionTokenHasExpiredException;
use App\Http\ControllerTraits\DisableWalletServiceTrait;
use App\Exceptions\TransactionTokenNotFoundInTheHeaderException;
use Domain\Disbursement\Payout\Factories\ExternalPaymentProviderFactory;
use Domain\Disbursement\Payout\PayoutAction\Factories\PayoutActionFactory;
use Domain\Disbursement\Payout\DataTransferObjects\ExternalPaymentProviderData;
use Domain\Disbursement\Payout\PayoutAction\DataTransferObjects\PayoutActionDTO;
use App\Helpers\AccountNameValidations\Factories\ExternalNameValidationProviderFactories;
use App\Helpers\AccountNameValidations\DataTransferObjects\ExternalNameValidationProviderData;

class CashoutV2Controller extends Controller
{

     use DisableWalletServiceTrait, VerifiesOtp;

    /**
     * @throws CustomModelNotFoundException
     */
    public function getByCountry(): JsonResponse
    {
        //Early return if no 'country' parameter is not provided
        if(! request()->exists('country') ) {

            return $this->respond([
                'status' => 200,
                'data' => CashOutMethodResource::collection(CashOutMethod::where('disabled_at', null)->get()),
                'message' => 'Cashout methods retrieved successfully.',
            ]);

        }

        // Proceed with the rest of the method if 'country' parameter is provided
        $country_name = htmlentities(request()->country);
        $wallet_currency = strtoupper(htmlentities(request()->currency));

        $countryCurrency = resolveCurrencyToCountryCurrency( $wallet_currency );

        $country = Country::where('name', $country_name)->first();

        if (blank($country)) {
            throw new CustomModelNotFoundException('No country found with the name specified');
        }

        if (blank($countryCurrency)) {
            throw new CustomModelNotFoundException("No currency {$wallet_currency} found with the name specified");
        }

        if (request()->has('currency')) {
            $cashoutmethods = $country->getCashoutMethods($wallet_currency, $countryCurrency);
        } else {
            $cashoutmethods = $country->getCashoutMethods();
        }

        return $this->respond([
            'status' => 200,
            'data' => CashOutMethodResource::collection($cashoutmethods),
            'message' => "Cashout methods in {$country_name} which support {$wallet_currency} retrieved successfully.",
        ]);

    }

    /**
     * @throws ContainerExceptionInterface
     * @throws CustomBadRequestException
     * @throws \Throwable
     * @throws WalletBalanceInsufficientException
     * @throws TransactionTokenNotFoundInTheHeaderException
     * @throws SomethingWentWrongException
     * @throws NotFoundExceptionInterface
     * @throws CustomModelNotFoundException
     * @throws TransactionLimitException
     * @throws TransactionTokenHasExpiredException
     * @throws KycFailedException
     */
    public function cashout(): JsonResponse
    {
        throw_if(
            blank(request()->otp),
            new CustomBadRequestException('OTP is required for this action')
        );

        $this->verifyOtp();

        TransactionToken::isAlive();
        Blockers::preventNegative();
        Blockers::validKycLevel();

        $amount = request('amount');
        $paymentMethod = $this->payoutMethod();
        $wallet = $this->getWallet();
        $walletType = $this->getWallet()->wallettype;
        $countryCurrency = $walletType->country_currency;

        Guard::againstMaxLimit($paymentMethod, $wallet, $amount, 'cashout');
        Guard::againstFullWithdrawal($wallet);

        Blockers::validateTransactionTime();

        $this->disableWalletServices($wallet, 'withdraw_state');

        if (request()->has('device_id')) {
            Blockers::validDeviceId(request('device_id'));
        }

        $accountId = $this->validateMomoAccountIdFormat( $paymentMethod );

        $accountName = request('payee_name') ?? $this->lookupAccountId($paymentMethod, $accountId);

        // prep the data for the external payment provider
        $externalProviderData = [
            'cashOutMethod' => $paymentMethod,
            'description' => request('reason'),
            'amount' => request('amount'),
            'countryCurrency' => $countryCurrency,
            'momoNumber' => request('momo_number'),
            'accountId' => $accountId,
            'payeeName' => $accountName,
        ];
        $externalPaymentProviderData = ExternalPaymentProviderData::toDTO($externalProviderData);
        $provider = ExternalPaymentProviderFactory::getExternalPaymentProvider($externalPaymentProviderData);
        $provider = $provider->handle($wallet);


        //Prep the payload for the payout action
        $payoutActionPayload = [
            'provider' => $provider,
            'cashOutMethod' => $paymentMethod,
            'description' => request('reason'),
            'amount' => request('amount'),
            'countryCurrency' => $countryCurrency,
            'momoNumber' => request('momo_number'),
            'accountId' => $accountId,
            'payeeName' =>  $accountName,
        ];
        $payload = PayoutActionDTO::toDTO($payoutActionPayload);
        $payoutAction = PayoutActionFactory::getPayoutHandler($payload);
        $response = $payoutAction->handle($wallet);


        $responseData = [
            'status' => $response['statusCode'],
            'message' => $response['description'] ?? null,
            'httpCode' => $response['httpCode'] ?? null,
            'bank_details' => $response['bank_details'] ?? [],
            'data' => new WalletResource($response['wallet']),
            'websocket_token' => null,
            'transaction_id' => null,
            'socket_channel' => null,
        ];

        if ($response['httpCode'] === 200) {

            $walletTransaction = Arr::get($response, 'data.walletTransaction');
        
            $receipt = $this->createReceipt($walletTransaction);
        
            $identifier = floor($receipt->amount + $receipt->number);
        
            $token = (new Hashids('#comToken21', 10))->encode($identifier);
        
            $receipt->websocket_token = Hash::make($token);
            $receipt->save();

            $responseData['websocket_token'] = $token;
            $responseData['socket_channel'] = 'payouts.'.$receipt->number;
            $responseData['transaction_id'] = $walletTransaction->transaction_id;

        }

        if (request()->get('is_counter')) {
            $responseData['reference'] = $response['reference'] ?? '';
            $responseData['linkingreference'] = $response['linkingreference'] ?? '';
        }

        return $this->respond($responseData);
    }

    private function payoutMethod()
    {
        return CashOutMethodRepository::find( request()->cash_out_method_id); 
    }

    private function getWallet(): ?Wallet
    {
        $wallet = WalletRepository::find(request()->wallet_id);

        if ( blank($wallet) ) {
            throw new CustomModelNotFoundException('No wallet found with the id specified');
        }

        return $wallet;
    }

    private function createReceipt(WalletTransaction $walletTransaction)
    {
        return PaymentReceipt::create([
            'number' => makeReceiptNumber(),
            'payer_contact' => auth()->user()->phone,
            'order_total' => $walletTransaction->amount_in_figures,
            'amount' => $walletTransaction->amount_in_figures,
            'client_id' => $walletTransaction->client_id,
            'wallet_transaction_id' => $walletTransaction->id,
            'status' => 'pending',
            'description' => 'Payout',
        ]);
    }


    private function getBeneficiaryName(array $beneficiary = []): ?string
    {
        return Arr::get($beneficiary, 'firstname') . ' ' . Arr::get($beneficiary, 'lastname');
    }

    private function lookupAccountId(CashOutMethod $payoutMethod, string $accountId): ?string
    {
        $payload = [
            'cashOutMethod' => $payoutMethod,
            'accountId' => $accountId
        ];

        $dto = ExternalNameValidationProviderData::toDTO( $payload );

        $response = ExternalNameValidationProviderFactories::getProvider($dto )->validate();

        $accountName = Arr::get($response, 'accountName');

        return !blank($accountName) ? $accountName : $this->getBeneficiaryName( request()->beneficiary);
    }

    /**
     * @throws CustomBadRequestException
     */
    private function validateMomoAccountIdFormat(CashOutMethod $payoutMethod): ?string
    {
        $accountId = null;
        
        if ( strtolower( $payoutMethod->fund_type ) === 'momo' ) {

            if( ! Str::startsWith($accountId, '+') ) {
                throw new CustomBadRequestException("For mobile money payouts, the MSISDN must start with '+' followed by the country code and subscriber number (e.g., +233201234567)");
            }

            $accountId = request('momo_number');

        } else {

            $accountId = request('bank_account_number');

        }

        return $accountId;
    }
}
