<?php

namespace App\Jobs;

use App\Services\TelegramBotHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessTelegramUpdateJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $update
     */
    public function __construct(public array $update) {}

    public function handle(TelegramBotHandler $handler): void
    {
        $handler->handle($this->update);
    }
}
