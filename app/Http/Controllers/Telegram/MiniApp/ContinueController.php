<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use App\Services\CoursePathService;
use App\Support\TelegramCopy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ContinueController extends Controller
{
    public function show(CoursePathService $path): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);

        $course = $this->resolveContinueCourse($user);

        if ($course === null) {
            return redirect()->route('tg.browse');
        }

        $resource = $path->nextStep($user, $course);

        if ($resource === null) {
            return redirect()->route('tg.courses.show', $course);
        }

        return view('telegram.mini-app.continue', [
            'copy' => $copy,
            'user' => $user,
            'resource' => $resource,
            'course' => $course,
            'activeNav' => 'continue',
        ]);
    }

    protected function resolveContinueCourse(User $user): ?Course
    {
        $download = $user->downloads()
            ->with(['learningResource.course'])
            ->latest('id')
            ->first();

        $course = $download?->learningResource?->course;

        if ($course !== null) {
            return $course;
        }

        return $user->courses()->orderBy('courses.name')->first();
    }
}
