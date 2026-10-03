<?php

namespace App\Jobs;

use App\Services\TelegramBotHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FlushTelegramAdminPublishBuffer implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $telegramId,
        public int|string $chatId,
        public int $updatedAtToken,
    ) {
        $this->delay(now()->addSeconds(2));
    }

    public function handle(TelegramBotHandler $handler): void
    {
        $handler->flushAdminPublishBuffer($this->telegramId, $this->chatId, $this->updatedAtToken);
    }
}
