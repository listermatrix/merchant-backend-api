<?php

namespace App\Jobs;

use App\Exceptions\CustomBadRequestException;
use Domain\PayMerchant\Trait\ReversesPayments;
use Exception;
use App\Models\Wallet;
use Illuminate\Support\Arr;
use Illuminate\Bus\Queueable;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use App\Models\BrijXServiceTransaction;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\MoniesHeldForSwapTransfer;
use App\Models\RemittanceDestinationRail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Domain\BXBank\MoneyTransfer\Transact\Dtos\RemittanceData;
use App\Http\Controllers\BrijxThirdparty\BuyBrijxOfferForRemittance;
use App\Models\BrijxIntentFulfillmentRequest;
use Domain\BXBank\MoneyTransfer\Transact\Factories\RemitDestinationFactory;

class RemitMoneyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReversesPayments, SerializesModels;

    public Wallet $wallet;

    public WalletTransaction $trx;

    public BrijXServiceTransaction $service;

    public function __construct(
        Wallet $wallet,
        WalletTransaction $trx,
        BrijXServiceTransaction $service)
    {
        $this->onQueue('brijxmoneytransfer');

        $this->wallet = $wallet;

        $this->trx = $trx;

        $this->service = $service;
    }

    public function handle()
    {
        $trx = $this->trx;

        $wallet = $this->wallet;

        $service = $this->service->refresh();

        if ($this->isCrossCurrency($service)) {

            $intent = BrijxIntentFulfillmentRequest::whereBrijxServiceTransactionId($service->id)->first();

            $offerPurchased = false;

            try {

                $offerPurchased = (new BuyBrijxOfferForRemittance)->execute($service, $intent);

            } catch (Exception $e) {
                Log::channel('brxbillpayment')->info($e->getMessage()." $service->brij_transaction_id");

                return throw new CustomBadRequestException($e->getMessage(), 1);
                

                // $this->reverseReceivedPayment($service, false);
            }

            if ($offerPurchased) {

                $offerId = Arr::get($service->request, 'initiate.payment.offerId');

                $moneyHeldForSwap = MoniesHeldForSwapTransfer::whereUuid($offerId)->first();

                $railConfig = RemittanceDestinationRail::whereUuid(Arr::get($moneyHeldForSwap->request,'rail_id'))->first();

                $outBoundRemittanceData = RemittanceData::toDTO(['moneyHeldForSwap' => $moneyHeldForSwap, 'railConfig' => $railConfig, 'serviceTransaction' => $service, 'wallet' => $wallet, 'intent' => $intent]);

                $remitDestination = RemitDestinationFactory::getDestination($outBoundRemittanceData);

                return $remitDestination->handle($service);

            }
        }
    }

    private function isCrossCurrency($service): bool
    {
        return $service->source_currency !== $service->destination_currency;
    }
}
