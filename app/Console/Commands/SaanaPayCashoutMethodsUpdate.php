<?php

namespace App\Console\Commands;

use Database\Seeders\SaanaPAYNGCashoutMethodTableSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SaanaPayCashoutMethodsUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:saanapay-cashout-methods';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates cashout methods for saanapay';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        Artisan::call('db:seed', ['--class' => SaanaPAYNGCashoutMethodTableSeeder::class]);
        $this->info('SaanaPAYNGCashoutMethodTableSeeder has been run.');

        return  0;
    }
}
