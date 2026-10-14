<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Http\Controllers\Controller;
use App\Support\TelegramCopy;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UseBotController extends Controller
{
    public function __invoke(): View
    {
        $user = Auth::user();
        $copy = $user !== null ? TelegramCopy::for($user) : new TelegramCopy;

        return view('telegram.mini-app.use-bot', [
            'copy' => $copy,
        ]);
    }
}
