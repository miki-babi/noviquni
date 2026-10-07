<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Enums\OpportunityType;
use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use App\Models\LearningResource;
use App\Models\Opportunity;
use App\Models\User;
use App\Support\TelegramCopy;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SavedController extends Controller
{
    /**
     * @return list<string>
     */
    public static function tabKeys(): array
    {
        return ['resources', 'scholarships', 'internships', 'jobs', 'mentorship'];
    }

    public function show(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);

        $tabKeys = self::tabKeys();
        $requestedTab = (string) $request->query('tab', '');
        $tab = in_array($requestedTab, $tabKeys, true)
            ? $requestedTab
            : $this->defaultTab($user);

        $bookmarks = $this->bookmarksForTab($user, $tab);

        return view('telegram.mini-app.saved', [
            'copy' => $copy,
            'user' => $user,
            'bookmarks' => $bookmarks,
            'tabs' => $tabKeys,
            'activeTab' => $tab,
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
            ->whereMorphedTo('bookmarkable', $resource)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return back()->with('status', $copy->get('saved.removed_status'));
        }

        $user->bookmarks()->firstOrCreate([
            'bookmarkable_type' => $resource->getMorphClass(),
            'bookmarkable_id' => $resource->id,
        ]);

        return back()->with('status', $copy->get('saved.saved_status'));
    }

    protected function defaultTab(User $user): string
    {
        foreach (self::tabKeys() as $tab) {
            if ($this->bookmarksForTab($user, $tab)->isNotEmpty()) {
                return $tab;
            }
        }

        return 'resources';
    }

    /**
     * @return Collection<int, Bookmark>
     */
    protected function bookmarksForTab(User $user, string $tab): Collection
    {
        $opportunityType = OpportunityType::fromSavedTabKey($tab);

        return $user->bookmarks()
            ->with(['bookmarkable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    LearningResource::class => ['course'],
                ]);
            }])
            ->latest('id')
            ->get()
            ->filter(function ($bookmark) use ($tab, $opportunityType): bool {
                $item = $bookmark->bookmarkable;

                if ($tab === 'resources') {
                    return $item instanceof LearningResource && $item->is_published;
                }

                return $opportunityType !== null
                    && $item instanceof Opportunity
                    && $item->is_published
                    && $item->type === $opportunityType;
            })
            ->values();
    }
}
