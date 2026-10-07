<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\User;
use App\Support\TelegramCopy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OpportunityController extends Controller
{
    public function show(Opportunity $opportunity): View
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);

        abort_unless($opportunity->is_published, 404);

        $isBookmarked = $user->bookmarks()
            ->whereMorphedTo('bookmarkable', $opportunity)
            ->exists();

        return view('telegram.mini-app.opportunity', [
            'copy' => $copy,
            'user' => $user,
            'opportunity' => $opportunity,
            'isBookmarked' => $isBookmarked,
            'activeNav' => 'saved',
        ]);
    }

    public function toggle(Request $request, Opportunity $opportunity): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);

        abort_unless($opportunity->is_published, 404);

        $existing = $user->bookmarks()
            ->whereMorphedTo('bookmarkable', $opportunity)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return back()->with('status', $copy->get('saved.removed_status'));
        }

        $user->bookmarks()->firstOrCreate([
            'bookmarkable_type' => $opportunity->getMorphClass(),
            'bookmarkable_id' => $opportunity->id,
        ]);

        return back()->with('status', $copy->get('saved.saved_status'));
    }
}
