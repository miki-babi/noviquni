<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\LearningResource;
use App\Models\User;
use App\Support\TelegramCopy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SavedController extends Controller
{
    public function show(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);

        $bookmarks = $user->bookmarks()
            ->with(['learningResource.course'])
            ->latest('id')
            ->get()
            ->filter(fn ($bookmark) => $bookmark->learningResource?->is_published)
            ->values();

        return view('telegram.mini-app.saved', [
            'copy' => $copy,
            'user' => $user,
            'bookmarks' => $bookmarks,
            'activeNav' => 'saved',
        ]);
    }

    public function toggle(Request $request, LearningResource $resource): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);

        abort_unless($resource->is_published, 404);

        $existing = $user->bookmarks()
            ->where('learning_resource_id', $resource->id)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return back()->with('status', $copy->get('saved.removed_status'));
        }

        $user->bookmarks()->firstOrCreate([
            'learning_resource_id' => $resource->id,
        ]);

        return back()->with('status', $copy->get('saved.saved_status'));
    }
}
