<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use App\Services\CoursePathService;
use App\Support\TelegramCopy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BrowseController extends Controller
{
    public function show(CoursePathService $path): View
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);
        $courses = $this->enrolledCourses($user);

        $withContent = $courses
            ->filter(fn (Course $course) => (int) $course->published_resources_count > 0)
            ->values();

        $state = match (true) {
            $courses->isEmpty() => 'empty',
            $withContent->isEmpty() => 'coming_soon',
            default => 'has_content',
        };

        $courseRows = ($state === 'has_content' ? $withContent : $courses)
            ->map(function (Course $course) use ($user, $path): array {
                $steps = $path->pathForUser($user, $course);
                $total = $steps->count();
                $completed = $steps->where('completed', true)->count();
                $next = $total > 0 ? $path->nextStep($user, $course) : null;
                $pathComplete = $total > 0 && $completed >= $total;

                return [
                    'course' => $course,
                    'next' => $next,
                    'completed' => $completed,
                    'total' => $total,
                    'path_complete' => $pathComplete,
                    'resource_count' => (int) $course->published_resources_count,
                ];
            })
            ->values();

        $continue = $courseRows
            ->first(fn (array $row): bool => $row['next'] !== null && ! $row['path_complete']);

        return view('telegram.mini-app.browse', [
            'copy' => $copy,
            'user' => $user,
            'courseRows' => $courseRows,
            'continue' => $continue,
            'state' => $state,
            'activeNav' => 'courses',
        ]);
    }

    /**
     * @return Collection<int, Course>
     */
    protected function enrolledCourses(User $user): Collection
    {
        return $user->courses()
            ->withCount([
                'learningResources as published_resources_count' => fn ($query) => $query->published(),
            ])
            ->orderBy('name')
            ->get();
    }
}
