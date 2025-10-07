<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\ListenTenantAssetActions::class,
        \App\Console\Commands\NotifyCriticallyBasedAssetSchedule::class,
        \App\Console\Commands\NotifyMaintenanceTasksAssetSchedule::class,
        \App\Console\Commands\ProcessPaymentRetries::class,
    ];
    
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('check:asset-expiry')->dailyAt('00:30');
        $schedule->command('notify:critically-based-asset-schedule')->everyMinute()->runInBackground();
        $schedule->command('notify:maintenance-tasks-asset-schedule')->everyMinute()->runInBackground();
        // Process payment retries and renewals daily at 12:30 AM
        $schedule->command('payment:process-retries')
                 ->dailyAt('00:30')
                 ->withoutOverlapping()
                 ->onOneServer()
                 ->appendOutputTo(storage_path('logs/payment-retries.log'));

        // Send payment reminders daily at 9:00 AM
        $schedule->command('payment:process-retries')
                 ->dailyAt('09:00')
                 ->withoutOverlapping()
                 ->onOneServer()
                 ->appendOutputTo(storage_path('logs/payment-reminders.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
