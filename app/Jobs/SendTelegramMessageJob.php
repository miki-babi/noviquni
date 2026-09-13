<?php

namespace App\Jobs;

use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendTelegramMessageJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int|string $chatId,
        public string $text,
        public array $payload = [],
    ) {}

    public function handle(TelegramService $telegram): void
    {
        $telegram->sendMessage($this->chatId, $this->text, $this->payload);
    }
}
