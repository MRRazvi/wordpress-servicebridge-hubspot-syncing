<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('hs:owners')->hourly()->withoutOverlapping();
        $schedule->command('sb:sync')->everyTenMinutes()->withoutOverlapping();
        $schedule->command('hs:sync')->everyFiveMinutes()->withoutOverlapping();

        $schedule->command('telescope:prune --hours=48')->daily();
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
