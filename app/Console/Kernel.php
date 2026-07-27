<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * 読書計画処理コマンドを毎日00:00に重複実行なしで登録する。
     *
     * @param  Schedule  $schedule  コマンドスケジュール
     *
     * 戻り値はない。
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('reading-plans:process')
            ->dailyAt('00:00')
            ->withoutOverlapping();
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
