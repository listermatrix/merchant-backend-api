<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{


    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {

        $schedule->command('requery:saanapay-cashout')->everyMinute()->withoutOverlapping();
        $schedule->command('update:saanapay-cashout-methods')->weekly()->withoutOverlapping();
        $schedule->command('requery:pending-saanapay-transaction')->everyTwoMinutes()->withoutOverlapping();
        $schedule->command('saanapay:requery-brij-send-kcb')->everyThirtyMinutes()->withoutOverlapping();
    }


    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
