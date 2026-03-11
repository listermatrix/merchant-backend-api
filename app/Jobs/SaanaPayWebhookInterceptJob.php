<?php

namespace App\Jobs;

use App\Models\DirectBankTransferTemporaryAccount;
use App\Models\WalletTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\ProcessWebhookJob as SpatieProcessWebhookJob;

class SaanaPayWebhookInterceptJob extends SpatieProcessWebhookJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected DirectBankTransferTemporaryAccount $directBankTransferTemporaryAccount;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $webhookResponse = $this->webhookCall->payload;

        Log::channel('saanapaywebhooks')->info('SaanaPay webhook intercepted', [
            'transaction_id' => Arr::get($webhookResponse, 'merchant_transaction_ref'),
            'webhook_response' => $webhookResponse,
        ]);

        $trxId = Arr::get($webhookResponse, 'merchant_transaction_ref');

        $trx = WalletTransaction::whereTransactionId($trxId)->first();

        if (blank($trx) || in_array($trx->status, ['successful', 'failed'])) {
            return;
        }

        $serviceId = $this->getServiceId($trxId);

        if ($serviceId === 'BRXTRANSFER') {
            $data = $this->getBrxTrxData();
            $status = Arr::get($webhookResponse, 'status');
            if (($status === 'successful')) {
                CompleteSaanaPayViaBrijSendJob::dispatch($data);
            } else {
                Log::channel('saanapay')->info('Brij Send transfer not successful', $webhookResponse);

                return;
            }
        }

    }

    private function getServiceId(string $transactionId): string
    {
        $this->directBankTransferTemporaryAccount = DirectBankTransferTemporaryAccount::whereTransactionId($transactionId)
            ->first();

        return $this->directBankTransferTemporaryAccount->service_id;
    }

    private function getBrxTrxData()
    {

        $transactionId = $this->directBankTransferTemporaryAccount->transaction_id;

        $serviceId = $this->directBankTransferTemporaryAccount->service_id;

        return [
            'serviceId' => $serviceId,
            'accountNumber' => $this->directBankTransferTemporaryAccount->account_number,
            'transactionId' => $transactionId,
        ];
    }
}
