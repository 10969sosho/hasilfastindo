<?php

namespace App\Console;

use App\Console\Commands\SyncBusinessCentralCommand;
use Illuminate\Contracts\Console\Kernel as KernelContract;
use Illuminate\Foundation\Console\Kernel;

class Kernel extends Kernel implements KernelContract
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        SyncBusinessCentralCommand::class,
    ];

    /**
     * Define your application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     */
    protected function schedule(Schedule $schedule): void
    {
        //
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require __DIR__.'/../routes/console.php';
    }
}
