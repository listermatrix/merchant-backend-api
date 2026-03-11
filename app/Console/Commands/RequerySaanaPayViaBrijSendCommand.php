<?php

namespace App\Console\Commands;

use App\Models\Wallet;
use Illuminate\Support\Arr;
use App\Jobs\RemitMoneyJob;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Domain\ApiClients\SaanaPay;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\KcbRemittanceRequeryJob;
use App\Models\BrijXServiceTransaction;
use App\Jobs\KcbBankRemittanceRequeryJob;
use App\Exceptions\CustomBadRequestException;
use App\Models\BrijxIntentFulfillmentRequest;
use App\Models\DirectBankTransferTemporaryAccount;

class RequerySaanaPayViaBrijSendCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $allowedStatuses = ['pending', 'initiated', 'processing'];
    
    protected $signature = 'saanapay:requery-brij-send-kcb';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Requery SaanaPay transactions initiated via Brij Send';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        BrijXServiceTransaction::query()
            ->whereIn('status', ['pending', 'initiated', 'processing'])
            ->whereDate('created_at', today()->format('Y-m-d'))
            ->where([
                'source_currency' => 'NGN',
            ])
            ->whereNotNull('payment_acc_id')
            ->chunkById(100, function ($services) {
                $services->each(function ($serviceTrx) {

                    $tempAccount = DirectBankTransferTemporaryAccount::where('service_id', 'BRXTRANSFER')
                        ->where('account_number', $serviceTrx->payment_acc_id)
                        ->first();

                    if (! $tempAccount) {
                        return;
                    }

                    $walletTransaction = $tempAccount->wallettransaction;
                  
                    if (blank($walletTransaction)) {
                        return;
                    }

                    $processor = $walletTransaction?->processor;
                    
                    if ($processor !== 'saanapay') {
                        return;
                    }

                    if ($walletTransaction->status === 'successful') {
                        $this->requeryKcbTransaction($serviceTrx->brij_transaction_id);

                        return;
                    }
                    if ($walletTransaction->status === 'failed') {
                        $this->handleFailedTransaction($tempAccount, $walletTransaction);

                        return;
                    }

                    $saanaPay = app(SaanaPay::class);
                    $response = $saanaPay->requeryTransaction($walletTransaction->transaction_id);

                    $status = app()->environment('local') ? 'successful' : Arr::get($response, 'data.status');

                    match ($status) {
                        'successful' => $this->handleSuccessfulTransaction($tempAccount, $walletTransaction),
                        'failed' => $this->handleFailedTransaction($tempAccount, $walletTransaction),
                        'pending' => $this->handlePendingTransaction($tempAccount, $walletTransaction),
                        default => $this->handleUnknownTransaction($tempAccount, $walletTransaction),
                    };
                });
            });

        return 0;
    }

    private function handleSuccessfulTransaction($tempAccount, $tx)
    {
        $amount = $tx->amount_in_figures;
        $accountNumber = $tempAccount->account_number;

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

        $tempAccount->expired_at = Carbon::now();
        $tempAccount->save();

        $wallet->refresh();
        $tempAccount->refresh();
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

    private function handleFailedTransaction($tempAccount, $tx)
    {
        $amount = $tx->amount_in_figures;
        $accountNumber = $tempAccount->account_number;

        $tx->status = 'failed';
        $tx->status_reason = 'Transaction failed at SaanaPay.';
        $tx->save();

        $tempAccount->expired_at = Carbon::now();
        $tempAccount->save();

        Log::channel('saanapay')->info('Transaction marked as failed', [
            'transaction_id' => $tx->transaction_id,
        ]);

        $service = BrijXServiceTransaction::wherePaymentAccId($accountNumber)->first();

        $service->status = 'failed';
        $service->payment_for_service_wallet_transaction_id = $tx->id;
        $service->save();

        $intent = BrijxIntentFulfillmentRequest::whereBrijxServiceTransactionId($service->id)->first();
        $intent->payment_for_intent_status = 'failed';
        $intent->status_reason = 'Payment failed for intent to be fulfilled.';
        $intent->save();
    }

    private function handlePendingTransaction($tempAccount, $tx)
    {
        Log::channel('saanapay')->info('Transaction still pending', [
            'transaction_id' => $tx->transaction_id,
        ]);
    }

    private function handleUnknownTransaction($tempAccount, $tx)
    {
        Log::channel('saanapay')->info('Transaction status unknown', [
            'transaction_id' => $tx->transaction_id,
        ]);
    }

    private function requeryKcbTransaction($transactionId)
    {
        $walletTrx = $this->validateTransaction($transactionId);

        $trxId = $walletTrx->id;
        $kcbId = $walletTrx->meta;
        $kcbId = trim((string) $kcbId, '"');
        $forceStatus = $request->forceStatus ?? null;

        // Dispatch the appropriate job
        $jobClass = match ($walletTrx->transaction_method){
            'momo' => KcbRemittanceRequeryJob::class,
            'kebank' => KcbBankRemittanceRequeryJob::class,
        };

        $jobClass::dispatch($trxId, $kcbId, $forceStatus);
    }

    private function validateTransaction(string $transactionId): WalletTransaction
    {
        $walletTrx = WalletTransaction::where('transaction_id', $transactionId)->first();

        throw_if(
            blank($walletTrx),
            new CustomBadRequestException('Unknown transaction')
        );

        $status = $walletTrx->status;

        throw_if(
            !in_array($status, $this->allowedStatuses),
            new CustomBadRequestException("Transaction is in its final state")
        );

        return $walletTrx;
    }
}
