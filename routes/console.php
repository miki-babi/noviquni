<?php

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Services\BroadcastService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    $broadcasts = app(BroadcastService::class);

    Broadcast::query()
        ->where('status', BroadcastStatus::Scheduled)
        ->whereNotNull('scheduled_at')
        ->where('scheduled_at', '<=', now())
        ->each(function (Broadcast $broadcast) use ($broadcasts): void {
            $broadcasts->queueSend($broadcast);
        });
})->everyMinute()->name('dispatch-scheduled-broadcasts')->withoutOverlapping();

Schedule::command('queue:work database --stop-when-empty --max-time=50 --sleep=1')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('process-queue');

Schedule::command('broadcasts:process-pending --limit=50')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('process-pending-broadcasts');

Schedule::command('daily:challenge')
    ->dailyAt('00:00')
    ->timezone('Africa/Nairobi')
    ->withoutOverlapping()
    ->name('generate-daily-challenge');

Schedule::command('daily:challenge-remind')
    ->dailyAt('18:00')
    ->timezone('Africa/Nairobi')
    ->withoutOverlapping()
    ->name('remind-daily-challenge');
