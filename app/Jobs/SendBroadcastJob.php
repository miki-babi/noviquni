<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Services\BroadcastService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendBroadcastJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Broadcast $broadcast) {}

    public function handle(BroadcastService $broadcasts): void
    {
        $broadcasts->send($this->broadcast->fresh());
    }
}
