<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use App\Support\TelegramCopy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BrowseController extends Controller
{
    public function show(): View
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

        return view('telegram.mini-app.browse', [
            'copy' => $copy,
            'user' => $user,
            'courses' => $state === 'has_content' ? $withContent : $courses,
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
