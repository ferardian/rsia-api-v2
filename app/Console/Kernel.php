<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('rsia:notif-undangan')->dailyAt('05:00');
        $schedule->command('rsia:notif-resep')->everyMinute();
        $schedule->command('rsia:remind-obat')->everyMinute();
        $schedule->command('rsia:remind-janji --h1')->dailyAt('17:00');
        $schedule->command('rsia:remind-janji --h0')->dailyAt('06:00');
        // $schedule->command('rsia:ppra-wa-notif')->everyMinute(); // DISABLED: Too many notifications
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
