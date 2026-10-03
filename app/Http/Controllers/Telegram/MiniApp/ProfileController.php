<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Enums\TelegramLocale;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Semester;
use App\Models\Stream;
use App\Models\University;
use App\Models\User;
use App\Services\OnboardingService;
use App\Services\ReferralService;
use App\Services\TelegramDeepLink;
use App\Support\TelegramCopy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(ReferralService $referrals, TelegramDeepLink $deepLinks): View
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);
        $user->load(['stream', 'university', 'semester', 'courses']);

        $bookmarks = $user->bookmarks()
            ->with(['learningResource.course'])
            ->latest('id')
            ->limit(8)
            ->get()
            ->filter(fn ($bookmark) => $bookmark->learningResource?->is_published)
            ->values();

        return view('telegram.mini-app.profile', [
            'copy' => $copy,
            'user' => $user,
            'courses' => $user->courses,
            'bookmarks' => $bookmarks,
            'deepLinks' => $deepLinks,
            'referralLink' => $referrals->referralLink($user),
            'premiumEnabled' => (bool) config('services.telegram.premium_enabled'),
            'activeNav' => 'profile',
        ]);
    }

    public function edit(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);
        $user->load(['courses']);

        $streams = Stream::query()->active()->orderBy('name')->get();
        $universities = University::query()->active()->orderBy('sort_order')->orderBy('name')->get();
        $semesters = Semester::query()->orderBy('sort_order')->orderBy('name')->get();
        $courses = Course::query()
            ->active()
            ->whereIn('stream_id', $streams->pluck('id'))
            ->orderBy('name')
            ->get();

        return view('telegram.mini-app.profile-edit', [
            'copy' => $copy,
            'user' => $user,
            'streams' => $streams,
            'universities' => $universities,
            'semesters' => $semesters,
            'courses' => $courses,
            'selectedCourseIds' => $user->courses->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'activeNav' => 'profile',
        ]);
    }

    public function updateAcademic(Request $request, OnboardingService $onboarding): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);

        $validated = $request->validate([
            'stream_id' => [
                'required',
                'integer',
                Rule::exists('streams', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'university_id' => [
                'nullable',
                'integer',
                Rule::exists('universities', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'semester_id' => [
                'nullable',
                'integer',
                Rule::exists('semesters', 'id'),
            ],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => [
                'integer',
                Rule::exists('courses', 'id')->where(function ($query) use ($request): void {
                    $query->where('is_active', true)
                        ->where('stream_id', (int) $request->input('stream_id'));
                }),
            ],
        ]);

        $user->update([
            'stream_id' => (int) $validated['stream_id'],
            'university_id' => isset($validated['university_id']) ? (int) $validated['university_id'] : null,
            'semester_id' => isset($validated['semester_id']) ? (int) $validated['semester_id'] : null,
        ]);

        $onboarding->syncCourses($user->fresh(), array_map('intval', $validated['course_ids'] ?? []));

        return redirect()
            ->route('tg.profile')
            ->with('status', $copy->get('settings.academic_saved'));
    }

    public function toggleNotifications(): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $user->update(['notifications_enabled' => ! $user->notifications_enabled]);
        $user->refresh();
        $copy = TelegramCopy::for($user);
        $state = $user->notifications_enabled
            ? $copy->get('notify.on')
            : $copy->get('notify.off');

        return back()->with('status', $copy->get('notify.toggled', ['state' => $state]));
    }

    public function enableNotifications(): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);

        if ($user->notifications_enabled) {
            return back()->with('status', $copy->get('notify.already'));
        }

        $user->update(['notifications_enabled' => true]);

        return back()->with('status', $copy->get('notify.enabled'));
    }

    public function updateLocale(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'in:en,am'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $locale = TelegramLocale::from($validated['locale']);
        $user->update(['telegram_locale' => $locale->value]);

        $copy = TelegramCopy::for($user->fresh());

        return back()->with('status', $copy->get('settings.saved'));
    }

    public function updateTheme(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'in:light,dark'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $user->update(['theme' => $validated['theme']]);

        $copy = TelegramCopy::for($user->fresh());

        return back()->with('status', $copy->get('settings.theme_saved'));
    }
}
