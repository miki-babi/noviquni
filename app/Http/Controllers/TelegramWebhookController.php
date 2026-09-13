<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTelegramUpdateJob;
use App\Services\TelegramBotHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramBotHandler $handler): Response
    {
        $update = $request->all();

        if (config('queue.default') === 'sync') {
            $handler->handle($update);
        } else {
            ProcessTelegramUpdateJob::dispatch($update);
        }

        return response('ok');
    }
}
