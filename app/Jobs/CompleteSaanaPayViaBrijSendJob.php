<?php

namespace App\Jobs;

use App\Models\Wallet;
use App\Jobs\RemitMoneyJob;
use Illuminate\Support\Arr;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use App\Models\BrijXServiceTransaction;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Exceptions\CustomBadRequestException;
use App\Models\BrijxIntentFulfillmentRequest;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Models\DirectBankTransferTemporaryAccount;

class CompleteSaanaPayViaBrijSendJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(public array $data)
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws CustomBadRequestException
     */
    public function handle()
    {
        $accountNumber = Arr::get($this->data, 'accountNumber');
        
        $temporaryBankAccount = DirectBankTransferTemporaryAccount::with('wallettransaction')->whereAccountNumber(
            $accountNumber
            )->first();
            
            if (!$temporaryBankAccount) {
                Log::channel('saanapay')->info('Temp account not found', $this->data);
                
                return;
            }
            
            $tx = $temporaryBankAccount->wallettransaction;
            $amount = $tx->amount_in_figures;
            
        DB::beginTransaction();

        try {

            $wallet = Wallet::whereId($tx->wallet_id)->lockForUpdate()->first();

            safelyCredit($wallet, $amount);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('saanapay')->info('DirectBankTransactionNotification', [
                'message' => 'Possible race condition detected',
                'webhook_request' => request()->all(),
            ]);

            throw new CustomBadRequestException($e->getMessage());
        }

        $tx->status = 'successful';
        $tx->wallet_balance += $amount;
        $tx->status_reason = 'User paid for money transfer.';
        $tx->save();

        $temporaryBankAccount->expired_at = Carbon::now();
        $temporaryBankAccount->save();

        $wallet->refresh();
        $temporaryBankAccount->refresh();
        $tx->refresh();

        $service = BrijXServiceTransaction::wherePaymentAccId($accountNumber)->first();

        $service->status = 'pending';
        $service->payment_for_service_wallet_transaction_id = $tx->id;
        $service->save();

        $intent = BrijxIntentFulfillmentRequest::whereBrijxServiceTransactionId($service->id)->first();
        $intent->payment_for_intent_status = 'successful';
        $intent->status_reason = 'Payment received for intent to be fulfilled.';
        $intent->save();

        RemitMoneyJob::dispatch($wallet, $tx, $service);
    }
}