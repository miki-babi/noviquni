<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Enums\ResourceHub;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\User;
use App\Support\TelegramCopy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function show(Course $course): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->courses()->where('courses.id', $course->id)->exists()) {
            return redirect()->route('tg.browse');
        }

        $copy = TelegramCopy::for($user);
        $hubs = collect([ResourceHub::Notes, ResourceHub::Modules, ResourceHub::Practice])
            ->map(function (ResourceHub $hub) use ($course, $copy): ?array {
                $count = LearningResource::query()
                    ->published()
                    ->where('course_id', $course->id)
                    ->whereIn('type', $hub->typeValues())
                    ->count();

                if ($count === 0) {
                    return null;
                }

                $label = match ($hub) {
                    ResourceHub::Notes => $copy->get('hub.notes'),
                    ResourceHub::Modules => $copy->get('hub.modules'),
                    ResourceHub::Practice => $copy->get('hub.quiz'),
                    ResourceHub::Exams => $copy->get('hub.quiz'),
                };

                return [
                    'hub' => $hub,
                    'label' => $label,
                    'count' => $count,
                ];
            })
            ->filter()
            ->values();

        return view('telegram.mini-app.course', [
            'copy' => $copy,
            'user' => $user,
            'course' => $course,
            'hubs' => $hubs,
            'activeNav' => 'browse',
        ]);
    }

    public function hub(Course $course, string $hub): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $resourceHub = ResourceHub::tryFrom($hub);

        if ($resourceHub === null || ! in_array($resourceHub, [ResourceHub::Notes, ResourceHub::Modules, ResourceHub::Practice], true)) {
            abort(404);
        }

        if (! $user->courses()->where('courses.id', $course->id)->exists()) {
            return redirect()->route('tg.browse');
        }

        $copy = TelegramCopy::for($user);
        $resources = LearningResource::query()
            ->published()
            ->where('course_id', $course->id)
            ->whereIn('type', $resourceHub->typeValues())
            ->orderBy('title')
            ->get();

        $title = match ($resourceHub) {
            ResourceHub::Notes => $copy->get('hub.notes'),
            ResourceHub::Modules => $copy->get('hub.modules'),
            ResourceHub::Practice => $copy->get('hub.quiz'),
            ResourceHub::Exams => $copy->get('hub.quiz'),
        };

        return view('telegram.mini-app.hub', [
            'copy' => $copy,
            'user' => $user,
            'course' => $course,
            'hub' => $resourceHub,
            'title' => $title,
            'resources' => $resources,
            'activeNav' => 'browse',
        ]);
    }
}
