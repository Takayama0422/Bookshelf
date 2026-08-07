<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * 読書計画処理コマンドを毎日20:00に登録する。
     *
     * @param  Schedule  $schedule  コマンドスケジュール
     *
     * 戻り値はない。
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('reading-plans:run-daily')
            ->daily()
            ->at('20:00');
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
