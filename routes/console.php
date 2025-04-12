<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 添加调度任务，每分钟执行一次清理黑名单命令（测试用）
Schedule::command('phone:clean-blacklist')
    ->everyMinute()
    ->appendOutputTo(storage_path('logs/scheduler.log'))
    ->onOneServer();

