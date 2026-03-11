<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WalletTransaction;
use App\Jobs\RequerySaanaPayViaFundWalletJob;
use App\Jobs\RequerySaanaPayViaPaymerchantJob;
use App\Jobs\RequerySaanaPayViaPayWithBrijJob;

class RequeryPendingSaanaPayTransactionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'requery:pending-saanapay-transaction';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to query pending saanapay tranasaction';

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
        WalletTransaction::where('status', 'pending')->where('processor', 'saanapay')
            ->whereIn('remark', ['paywithbrij', 'fund', ...collectionRemarks()] )
            ->whereIn('transaction_channel', ['card', 'directbanktransfer'])
            ->with('wallet')
            ->chunkById(50, function($transactions) {
                foreach ($transactions as $transaction) {

                    if($transaction->remark === 'paywithbrij') {
                        RequerySaanaPayViaPayWithBrijJob::dispatch($transaction->id);
                    } 
                    else if($transaction->remark === 'fund') {
                        RequerySaanaPayViaFundWalletJob::dispatch($transaction->id);
                    } 
                    else if(in_array($transaction->remark, collectionRemarks())) {
                        RequerySaanaPayViaPaymerchantJob::dispatch($transaction->id);
                    }
                }
            });

        return 0;
    }
}
