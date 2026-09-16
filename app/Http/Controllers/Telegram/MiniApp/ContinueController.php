<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TelegramCopy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ContinueController extends Controller
{
    public function show(): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);

        $download = $user->downloads()
            ->with(['learningResource.course'])
            ->latest('id')
            ->first();

        $resource = $download?->learningResource;

        if ($resource === null || ! $resource->is_published) {
            return redirect()->route('tg.browse');
        }

        return view('telegram.mini-app.continue', [
            'copy' => $copy,
            'user' => $user,
            'resource' => $resource,
            'course' => $resource->course,
            'activeNav' => 'continue',
        ]);
    }
}
