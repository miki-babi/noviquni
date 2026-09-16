<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $hasResume = $user->downloads()
            ->whereHas('learningResource', fn ($query) => $query->published())
            ->exists();

        return redirect()->route($hasResume ? 'tg.continue' : 'tg.browse');
    }
}
