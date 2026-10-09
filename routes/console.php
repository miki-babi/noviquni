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
            $broadcasts->send($broadcast);
        });
})->everyMinute()->name('dispatch-scheduled-broadcasts')->withoutOverlapping();
