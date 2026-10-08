<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Enums\TelegramLocale;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Opportunity;
use App\Models\Semester;
use App\Models\Stream;
use App\Models\University;
use App\Models\User;
use App\Services\OnboardingService;
use App\Services\ReferralService;
use App\Services\SettingsService;
use App\Services\TelegramDeepLink;
use App\Support\TelegramCopy;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(ReferralService $referrals, SettingsService $settings, TelegramDeepLink $deepLinks): View
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);
        $user->load(['stream', 'university', 'semester', 'courses', 'years']);

        $bookmarks = $user->bookmarks()
            ->with(['bookmarkable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    LearningResource::class => ['course'],
                ]);
            }])
            ->latest('id')
            ->limit(8)
            ->get()
            ->filter(function ($bookmark): bool {
                $item = $bookmark->bookmarkable;

                return ($item instanceof LearningResource && $item->is_published)
                    || ($item instanceof Opportunity && $item->is_published);
            })
            ->values();

        return view('telegram.mini-app.profile', [
            'copy' => $copy,
            'user' => $user,
            'courses' => $user->courses,
            'bookmarks' => $bookmarks,
            'deepLinks' => $deepLinks,
            'referralLink' => $referrals->referralLink($user),
            'qualifiedCount' => $referrals->qualifiedCount($user),
            'requiredReferrals' => $settings->requiredReferrals(),
            'referralPoints' => $user->referral_points,
            'activeNav' => 'profile',
        ]);
    }

    public function edit(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);
        $user->load(['courses', 'years']);

        $streams = Stream::query()->active()->orderBy('name')->get();
        $universities = University::query()->active()->orderBy('sort_order')->orderBy('name')->get();
        $semesters = Semester::query()->orderBy('sort_order')->orderBy('name')->get();
        $courses = Course::query()
            ->active()
            ->whereIn('stream_id', $streams->pluck('id'))
            ->orderBy('name')
            ->get();

        $selectedCourseIds = $user->courses->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($user->isFreshman() && $selectedCourseIds === []) {
            $selectedCourseIds = $courses->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return view('telegram.mini-app.profile-edit', [
            'copy' => $copy,
            'user' => $user,
            'streams' => $streams,
            'universities' => $universities,
            'semesters' => $semesters,
            'courses' => $courses,
            'selectedCourseIds' => $selectedCourseIds,
            'isFreshman' => $user->isFreshman(),
            'activeNav' => 'profile',
        ]);
    }

    public function updateAcademic(Request $request, OnboardingService $onboarding): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);
        $user->loadMissing('years');

        if (! $user->isFreshman()) {
            $validated = $request->validate([
                'department_name' => ['required', 'string', 'max:255'],
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
            ]);

            $user->update([
                'department_name' => trim($validated['department_name']),
                'university_id' => isset($validated['university_id']) ? (int) $validated['university_id'] : null,
                'semester_id' => isset($validated['semester_id']) ? (int) $validated['semester_id'] : null,
            ]);

            $onboarding->syncCourses($user->fresh(), []);

            return redirect()
                ->route('tg.profile')
                ->with('status', $copy->get('settings.academic_saved'));
        }

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
                Rule::exists('courses', 'id')->where(fn ($query) => $query->where('is_active', true)),
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
