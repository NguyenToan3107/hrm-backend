<?php

namespace App\Console;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Add 8 every month
        $schedule->command('user:update-time-off-hours')->monthlyOn(1, '01:00');

        // Calculate time off every year
        $schedule->command('user:reset-old-time-off')->cron('0 0 1 7 *');

        // Run end year
        $schedule->command('user:run-yearly-task')->yearlyOn(1, 1, '00:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
