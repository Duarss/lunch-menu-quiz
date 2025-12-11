<?php

namespace App\Console;

use App\Console\Commands\LockWeeklySelections;
use App\Console\Commands\GenerateKaryawanCsv;
use App\Console\Commands\ImportKaryawanUsers;
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
        // Lock upcoming week selections every Friday night
        $schedule->command('menus:lock-weekly-selections')
            ->timezone(config('app.timezone', 'Asia/Jakarta'))
            ->fridays()
            ->at('23:59');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
