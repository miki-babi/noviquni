<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Enums\ResourceHub;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\User;
use App\Services\StudyPlanService;
use App\Support\TelegramCopy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function show(Course $course, StudyPlanService $studyPlans): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->courses()->where('courses.id', $course->id)->exists()) {
            return redirect()->route('tg.browse');
        }

        $copy = TelegramCopy::for($user);
        $plans = $studyPlans->plansForUser($user, $course);

        $hubs = collect([
            ResourceHub::Modules,
            ResourceHub::Notes,
            ResourceHub::Practice,
            ResourceHub::Exams,
        ])
            ->map(function (ResourceHub $hub) use ($course): ?array {
                $count = LearningResource::query()
                    ->published()
                    ->where('course_id', $course->id)
                    ->whereIn('type', $hub->typeValues())
                    ->count();

                if ($count === 0) {
                    return null;
                }

                return [
                    'hub' => $hub,
                    'label' => $hub->label(),
                    'count' => $count,
                ];
            })
            ->filter()
            ->values();

        return view('telegram.mini-app.course', [
            'copy' => $copy,
            'user' => $user,
            'course' => $course,
            'plans' => $plans,
            'hubs' => $hubs,
            'activeNav' => 'courses',
        ]);
    }

    public function hub(Course $course, string $hub): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $resourceHub = ResourceHub::tryFrom($hub);

        if ($resourceHub === null || ! in_array($resourceHub, [
            ResourceHub::Notes,
            ResourceHub::Modules,
            ResourceHub::Practice,
            ResourceHub::Exams,
        ], true)) {
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
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return view('telegram.mini-app.hub', [
            'copy' => $copy,
            'user' => $user,
            'course' => $course,
            'hub' => $resourceHub,
            'title' => $resourceHub->label(),
            'resources' => $resources,
            'activeNav' => 'courses',
        ]);
    }
}
