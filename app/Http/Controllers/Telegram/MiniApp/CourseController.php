<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Enums\ResourceType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Telegram\MiniApp\Concerns\ResolvesMiniAppHub;
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
    use ResolvesMiniAppHub;

    public function show(Course $course, StudyPlanService $studyPlans): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->courses()->where('courses.id', $course->id)->exists()) {
            return redirect()->route('tg.browse');
        }

        $copy = TelegramCopy::for($user);
        $plans = $studyPlans->plansForUser($user, $course);

        $hubs = collect(ResourceType::creatableCases())
            ->map(function (ResourceType $type) use ($course): ?array {
                $count = LearningResource::query()
                    ->published()
                    ->where('course_id', $course->id)
                    ->where('type', $type->value)
                    ->count();

                if ($count === 0) {
                    return null;
                }

                return [
                    'hub' => $type,
                    'label' => $type->label(),
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
        $resolved = $this->resolveMiniAppHub($hub);

        if ($resolved === null) {
            abort(404);
        }

        if (! $user->courses()->where('courses.id', $course->id)->exists()) {
            return redirect()->route('tg.browse');
        }

        $copy = TelegramCopy::for($user);
        $resources = LearningResource::query()
            ->published()
            ->where('course_id', $course->id)
            ->whereIn('type', $resolved['types'])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return view('telegram.mini-app.hub', [
            'copy' => $copy,
            'user' => $user,
            'course' => $course,
            'hub' => $resolved['hub'],
            'title' => $resolved['hub']->label(),
            'resources' => $resources,
            'activeNav' => 'courses',
        ]);
    }
}
