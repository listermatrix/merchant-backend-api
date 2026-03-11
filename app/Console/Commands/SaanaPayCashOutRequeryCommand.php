<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WalletTransaction;
use App\Jobs\v2\SaanaPay\CompleteSaanaPayCashoutJob;

class SaanaPayCashOutRequeryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'requery:saanapay-cashout';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cashout re-query for SaanaPay';


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        WalletTransaction::query()
            ->whereIn('remark',['payout','api-payout'])->where('currency','NGN')
            ->where('status','pending')->where('processor', 'saanapay')
            ->chunkById(50,function ($transactions){
                foreach ($transactions as $transaction)
                {
                    CompleteSaanaPayCashoutJob::dispatch($transaction);
                }
            });

        return  0;
    }
}
