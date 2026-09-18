<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Enums\ResourceHub;
use App\Http\Controllers\Controller;
use App\Models\LearningResource;
use App\Models\User;
use App\Services\PremiumService;
use App\Support\TelegramCopy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LibraryController extends Controller
{
    public function show(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);
        $courseIds = $user->courses()->pluck('courses.id');

        $hubs = collect(ResourceHub::cases())
            ->map(function (ResourceHub $hub) use ($courseIds): array {
                $count = $courseIds->isEmpty()
                    ? 0
                    : LearningResource::query()
                        ->published()
                        ->whereIn('course_id', $courseIds)
                        ->whereIn('type', $hub->typeValues())
                        ->count();

                return [
                    'hub' => $hub,
                    'label' => $hub->label(),
                    'count' => $count,
                ];
            })
            ->values();

        return view('telegram.mini-app.library', [
            'copy' => $copy,
            'user' => $user,
            'hubs' => $hubs,
            'activeNav' => 'resources',
        ]);
    }

    public function hub(Request $request, string $hub, PremiumService $premium): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $resourceHub = ResourceHub::tryFrom($hub);

        if ($resourceHub === null) {
            abort(404);
        }

        $copy = TelegramCopy::for($user);
        $courseIds = $user->courses()->pluck('courses.id');
        $courseSlug = $request->string('course')->toString();
        $course = $courseSlug === ''
            ? null
            : $user->courses()->where('courses.slug', $courseSlug)->first();

        if ($courseSlug !== '' && $course === null) {
            abort(404);
        }

        $resources = $courseIds->isEmpty()
            ? collect()
            : LearningResource::query()
                ->published()
                ->with('course')
                ->whereIn('course_id', $courseIds)
                ->when($course !== null, fn ($query) => $query->where('course_id', $course->id))
                ->whereIn('type', $resourceHub->typeValues())
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
                ->sortBy(fn (LearningResource $resource): array => [
                    $resource->course?->name ?? '',
                    $resource->sort_order,
                    $resource->title,
                ])
                ->values();

        $groups = $resources
            ->groupBy(fn (LearningResource $resource): string => (string) ($resource->course?->name ?? 'Course'))
            ->map(fn ($items, string $courseName): array => [
                'course_name' => $courseName,
                'resources' => $items->map(fn (LearningResource $resource): array => [
                    'resource' => $resource,
                    'locked' => ! $premium->canAccess($user, $resource),
                ])->values(),
            ])
            ->values();

        return view('telegram.mini-app.library-hub', [
            'copy' => $copy,
            'user' => $user,
            'hub' => $resourceHub,
            'title' => $resourceHub->label(),
            'groups' => $groups,
            'activeNav' => 'resources',
        ]);
    }
}
