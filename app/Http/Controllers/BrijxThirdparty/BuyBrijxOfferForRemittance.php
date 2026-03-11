<?php

namespace App\Http\Controllers\BrijxThirdparty;

use App\Models\Wallet;
use App\Models\BrijXOffer;
use App\Models\WalletType;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use App\Models\WalletTransaction;
use App\Models\BrijXServiceTransaction;
use App\Models\MoniesHeldForSwapTransfer;
use App\Http\Actions\Wallet\CreateNewWallet;
use App\Exceptions\CustomBadRequestException;
use App\Models\BrijxIntentFulfillmentRequest;
use App\Models\CashInMethod;

class BuyBrijxOfferForRemittance
{
    protected mixed $walletToReceiveAmountBeingSoldTransaction;

    protected mixed $walletToReceivePurchasedOfferTransaction;

    private function getLegacyCurrency($currency)
    {
        $map = [
            'KES' => 'KSH',
            'GHS' => 'GHS',
            'NGN' => 'NGN',
            'XAF' => 'XAF',
            'TZS' => 'TZS'
        ];

        return Arr::get($map, $currency, $currency);
    }

    public function validate(BrijXServiceTransaction $service): array
    {
        $offerId = Arr::get($service->response, 'initiate.data.reference');

        $offer = BrijXOffer::whereUuid($offerId)->first();

        throw_if(
            $offer?->status !== BrijXOffer::ACTIVE,
            new CustomBadRequestException('Offer no longer available, try another offer.')
        );

        $walletUsedToPayTheOffer = Wallet::with('client')->whereId($service->wallet_paying_for_swap_offer_id)
            ->whereStatus('active')
            ->first();

        throw_if(
            ! $walletUsedToPayTheOffer,
            new CustomBadRequestException('Sorry! Wallet to pay for the offer cannot be found.')
        );

        $reservationExpiresAt = $offer->reservation_expires_at_date.' '.$offer->reservation_expires_at_time;

        $reservationExpiry = Carbon::createFromFormat('Y-m-d H:i:s', $reservationExpiresAt);

        $offerHasBeenReservedByAnotherUser = $offer->reserved_by_client_id != $walletUsedToPayTheOffer->client_id;
        $offerReservationDurationExpired = now()->greaterThan($reservationExpiry);

        throw_if(
            $offerHasBeenReservedByAnotherUser || $offerReservationDurationExpired,
            new CustomBadRequestException('Sorry! this offer reservation has expired.')
        );

        $walletUsedToPostTheOffer = Wallet::with('client')->whereId($offer->wallet_id)->whereStatus('active')->first();

        throw_if(
            ! $walletUsedToPostTheOffer,
            new CustomBadRequestException('Swap failed due to an internal error.')
        );

        $typeOfWalletUsedToPayOffer = WalletType::whereCurrency($this->getLegacyCurrency($offer->to_currency))
            ->where('is_internal', 0)
            ->first();

        $feeCode = $this->getFeeCode($typeOfWalletUsedToPayOffer);

        return [
            'offer' => $offer,
            'postedFrom' => $walletUsedToPostTheOffer,
            'payingFrom' => $walletUsedToPayTheOffer,
            'feeCode' => $feeCode,
            'serviceTransaction' => $service,
        ];
    }

    private function postOffer(
        Wallet $walletUsedToPostOffer,
        BrijXServiceTransaction $service
    )
    {
        $wallet = $walletUsedToPostOffer;

        $amountToPost = $service->destination_amount;

        $feeCode = $this->getFeeCode($wallet->wallettype);

        $totalPayable = $amountToPost;

        $walletCurrency = $this->getCurrency($wallet->currency);

        $transaction_amount = $amountToPost;
        $old_balance = $wallet->balance;

        //store the transaction
        //Debit has already been done during GetRate()
        //Escrow has also been affected in the GetRate() process

        $wallet->save();

        $moneyHeldId = Arr::get($service->request,'initiate.payment.offerId');

        $moneyHeld = MoniesHeldForSwapTransfer::whereUuid($moneyHeldId)->first();

        $moneyHeld->status = MoniesHeldForSwapTransfer::SOLD;
        $moneyHeld->expires_at = now();
        $moneyHeld->save();

        $transaction_id = makeGenericId();
        $currentTime = now();

        $expiresAt = today()->addDay();


        $offer = BrijXOffer::create([
            'wallet_transaction_id' => $transaction_id,
            'from_currency' => $walletCurrency,
            'to_currency' => $this->getCurrency($service->source_currency),
            'rate' => $moneyHeld->rate,
            'wallet_id' => $wallet->id,
            'is_brijx_express' => 0,
            'initial_offer_amount' => $moneyHeld->amount,
            'expires_at' => $expiresAt,
            'amount' => $moneyHeld->amount,
            'status' => BrijXOffer::ACTIVE,
            'reservation_expires_at_date' => $currentTime->toDateString(),
            'reservation_expires_at_time' => $currentTime->toTimeString(),
        ]);

        //log the transaction
        $transaction = WalletTransaction::create([
            'remark' => 'brijxoffer',
            'transaction_method' => 'currencyswaps',
            'transaction_channel' => 'newbrijxoffer',
            'currency' => $wallet->currency,
            'app_fee' => 0,
            'source_client_id' => $wallet->client->id,
            'status' => 'successful',
            'brijx_id' => $offer->id,
            'balance_before' => $moneyHeld->previous_balance,
            'wallet_balance' => $moneyHeld->balance_after,
            'status_reason' => 'Swap offer created successfully',
            'wallet_id' => $wallet->id,
            'transaction_id' => $transaction_id,
            'transaction_amount' => makeStringMoney($transaction_amount),
            'amount_in_figures' => $transaction_amount,
            'position' => 'debit',
        ]);

        // Transaction::charge($wallet, $amountToPost, $feeCode, notifyUser: false);

        return $offer;
    }

    private function getCurrency($currency, $order = 'forward')
    {
        $map = [
            'KSH' => 'KES',
            'GHS' => 'GHS',
            'NGN' => 'NGN',
            'XOF' => 'XOF',
            'XAF' => 'XAF'
        ];

        if ($order == 'forward') {
            return Arr::get($map, $currency, $currency);
        }

        if ($order == 'reverse') {
            return $currency;
        }
    }

    public function execute(
        BrijXServiceTransaction $service,
        ?BrijxIntentFulfillmentRequest $intent): ?bool
    {
        $serviceTransaction = $service;
        $paymentChannel = $this->getPaymentChannel($service);

        $walletUsedToPostTheOffer = Wallet::with('wallettype')->whereId($service->wallet_selling_the_offer_id)->first();
        $walletUsedToPayTheOffer  = Wallet::whereId($service->wallet_paying_for_swap_offer_id)->first();

        $feeCode = '';

        $offer = $this->postOffer($walletUsedToPostTheOffer, $service);

        $amountToSell = $serviceTransaction->amount;

        $amountToReceive = normalizeFloat($offer->amount);

        $fee = 0;

        $totalPayable = $amountToSell + $fee;

        //If brij wallet is being used to pay for the remittance
        //The initiate process has already debitted the customer
        //So there wont be a debit needed here again
        if(!$paymentChannel?->channel == 'brijwallet' )
        {
            throw_if(
                $walletUsedToPayTheOffer->balance < $totalPayable,
                new CustomBadRequestException('Sorry! You have insufficient balance in your '.$walletUsedToPayTheOffer->currency.' wallet.')
            );
        }

        $typeOfWalletToReceivePurchasedOffer = WalletType::whereCurrency($this->getLegacyCurrency($offer->from_currency))
            ->where('is_internal', 0)
            ->first();

        throw_if(
            ! $typeOfWalletToReceivePurchasedOffer,
            new CustomBadRequestException('Sorry! The currency wallet to receive the offer is not found')
        );

        if($intent)
        {
            $walletToReceivePurchasedOffer = Wallet::with('client')->whereId($intent->fulfillment_partner_wallet_id)
                ->whereStatus('active')
                ->first();
        } else {
            $walletToReceivePurchasedOffer = Wallet::with('client')->whereId($service->wallet_receiving_purchased_offer_id)
                ->whereStatus('active')
                ->first();
        }

        if (! $walletToReceivePurchasedOffer) {
            $walletToReceivePurchasedOffer = (new CreateNewWallet)->execute($typeOfWalletToReceivePurchasedOffer);
        }

        throw_if(
            $walletToReceivePurchasedOffer->client_id == $walletUsedToPostTheOffer->client_id,
            new CustomBadRequestException('Offer not available to the user who posted it.')
        );

        $walletToReceiveAmountBeingSold = Wallet::whereClientIdAndWalletTypeId($walletUsedToPostTheOffer->client_id, $walletUsedToPayTheOffer->wallet_type_id)
            ->first();

        throw_if(
            ! $walletToReceiveAmountBeingSold,
            new CustomBadRequestException('The receiving wallet is missing, please create.')
        );

        $walletUsedToPostTheOffer->brijx_escrow -= $amountToReceive;
        $walletUsedToPostTheOffer->save();

        $offer->status = BrijXOffer::HOLDING;
        $offer->save();

        //TODO: reduce the parameter list
        $this->handleWalletPayingForTheOffer($amountToSell, $walletUsedToPayTheOffer, $walletUsedToPostTheOffer, $offer, $paymentChannel);

        $this->handleWalletReceivingPurchasedOffer($intent, $walletToReceivePurchasedOffer, $walletUsedToPostTheOffer, $offer);

        $this->handleWalletReceivingAmountSold($amountToSell, $walletToReceiveAmountBeingSold, $walletToReceivePurchasedOffer, $offer);

        $offer->amount -= $amountToReceive;

        if ($offer->amount === $amountToReceive) {
            $offer->status = BrijXOffer::CLOSED;
        }
        else {
            $offer->status = BrijXOffer::ACTIVE;
        }


        $offer->save();
        return true;
    }

    private function getPaymentChannel(BrijXServiceTransaction $service): ?CashInMethod
    {
        $channelId = Arr::get($service->request, 'initiate.payment.payment_channel_id');

        return CashInMethod::whereId($channelId)->first();
    }

    private function handleWalletReceivingAmountSold(
        float $amountToSell,
        Wallet $walletToReceiveAmountBeingSold,
        Wallet $walletToReceivePurchasedOffer,
        BrijXOffer $offer
    ) {
        $walletToReceiveAmountBeingSoldPreviousBalance = $walletToReceiveAmountBeingSold->balance;
        safelyCredit($walletToReceiveAmountBeingSold, $amountToSell);
        $walletToReceiveAmountBeingSold->refresh();

        $this->walletToReceiveAmountBeingSoldTransaction = WalletTransaction::create([
            'remark' => 'brijxoffersell',
            'transaction_method' => 'currencyswaps',
            'transaction_channel' => 'brijxoffersold',
            'currency' => $this->getLegacyCurrency($offer->to_currency),
            'target_client_id' => $walletToReceiveAmountBeingSold->client->id,
            'source_client_id' => $walletToReceivePurchasedOffer->client->id,
            'status' => 'successful',
            'brijx_id' => $offer->id,
            'wallet_balance' => $walletToReceiveAmountBeingSold->balance,
            'balance_before' => $walletToReceiveAmountBeingSoldPreviousBalance,
            'status_reason' => 'Received from a purchased swap offer',
            'wallet_id' => $walletToReceiveAmountBeingSold->id,
            'transaction_id' => makeGenericId(),
            'transaction_amount' => makeStringMoney($amountToSell),
            'amount_in_figures' => $amountToSell,
            'position' => 'credit',
        ]);

    }

    private function handleWalletReceivingPurchasedOffer(
        BrijxIntentFulfillmentRequest $intent,
        Wallet $walletToReceivePurchasedOffer,
        Wallet $walletUsedToPostTheOffer,
        BrijXOffer $offer
    ) {
        $amountToReceive = $intent->destination_amount;
        $walletToReceivePurchasedOfferPreviousBalance = $walletToReceivePurchasedOffer->balance;
        safelyCredit($walletToReceivePurchasedOffer, $amountToReceive);
        $walletToReceivePurchasedOffer->refresh();

        $this->walletToReceivePurchasedOfferTransaction = WalletTransaction::create([
            'remark' => 'purchasebrijxoffer',
            'transaction_method' => 'currencyswaps',
            'transaction_channel' => 'brijxofferpurchased',
            'currency' => $this->getLegacyCurrency($offer->from_currency),
            'source_client_id' => $walletUsedToPostTheOffer->client->id,
            'target_client_id' => $walletToReceivePurchasedOffer->client->id,
            'status' => 'successful',
            'brijx_id' => $offer->id,
            'wallet_balance' => $walletToReceivePurchasedOffer->balance,
            'balance_before' => $walletToReceivePurchasedOfferPreviousBalance,
            'status_reason' => 'Received from swap offer purchase.',
            'wallet_id' => $walletToReceivePurchasedOffer->id,
            'transaction_id' => makeGenericId(),
            'transaction_amount' => makeStringMoney($amountToReceive),
            'amount_in_figures' => $amountToReceive,
            'position' => 'credit',
        ]);

        $intent->swapped_funds_credited_status = 'successful';
        $intent->save();

    }

    private function handleWalletPayingForTheOffer(
        float $amountToSell,
        Wallet $walletUsedToPayTheOffer,
        Wallet $walletUsedToPostTheOffer,
        BrijXOffer $offer,
        ?CashInMethod $paymentChannel
    ) {
        $walletUsedToPayTheOfferPreviousBalance = $walletUsedToPayTheOffer->balance + $amountToSell;

        //This is because , brij wallet has been debitted
        // before this step
        if($paymentChannel?->channel != 'brijwallet')
        {
            safelyDebit($walletUsedToPayTheOffer, $amountToSell);
            $walletUsedToPayTheOffer->refresh();
        }

        $walletUsedToPayTheOfferTransaction = WalletTransaction::create([
            'remark' => 'brijxofferpurchase',
            'transaction_method' => 'currencyswaps',
            'transaction_channel' => 'paybrijxoffer',
            'currency' => $this->getLegacyCurrency($offer->to_currency),
            'target_client_id' => $walletUsedToPostTheOffer->client->id,
            'source_client_id' => $walletUsedToPayTheOffer->client->id,
            'status' => 'successful',
            'brijx_id' => $offer->id,
            'wallet_balance' => $walletUsedToPayTheOffer->balance,
            'balance_before' => $walletUsedToPayTheOfferPreviousBalance,
            'status_reason' => 'Paid for a swap offer',
            'wallet_id' => $walletUsedToPayTheOffer->id,
            'transaction_id' => makeGenericId(),
            'transaction_amount' => makeStringMoney($amountToSell),
            'amount_in_figures' => $amountToSell,
            'position' => 'debit',
        ]);

    }

    private function getFeeCode(WalletType $walletType): ?string
    {
        $feeCodes = [
            'GHS' => 'BFXS01',
            'NGN' => 'BFXS02',
            'KSH' => 'BFXS03',
            'XOF' => 'BFXS04'
        ];

        return Arr::get($feeCodes, $walletType->currency);
    }
}
