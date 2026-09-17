<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Enums\ResourceHub;
use App\Http\Controllers\Controller;
use App\Models\LearningResource;
use App\Models\User;
use App\Support\TelegramCopy;
use Illuminate\Http\RedirectResponse;
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

    public function hub(string $hub): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $resourceHub = ResourceHub::tryFrom($hub);

        if ($resourceHub === null) {
            abort(404);
        }

        $copy = TelegramCopy::for($user);
        $courseIds = $user->courses()->pluck('courses.id');

        $resources = $courseIds->isEmpty()
            ? collect()
            : LearningResource::query()
                ->published()
                ->with('course')
                ->whereIn('course_id', $courseIds)
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

        return view('telegram.mini-app.library-hub', [
            'copy' => $copy,
            'user' => $user,
            'hub' => $resourceHub,
            'title' => $resourceHub->label(),
            'resources' => $resources,
            'activeNav' => 'resources',
        ]);
    }
}
