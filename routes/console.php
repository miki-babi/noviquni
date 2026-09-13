<?php

use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcastJob;
use App\Models\Broadcast;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    Broadcast::query()
        ->where('status', BroadcastStatus::Scheduled)
        ->whereNotNull('scheduled_at')
        ->where('scheduled_at', '<=', now())
        ->each(function (Broadcast $broadcast): void {
            $broadcast->update(['status' => BroadcastStatus::Sending]);
            SendBroadcastJob::dispatch($broadcast);
        });
})->everyMinute()->name('dispatch-scheduled-broadcasts')->withoutOverlapping();
