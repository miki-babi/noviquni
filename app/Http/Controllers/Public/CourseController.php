<?php

namespace App\Http\Controllers\Public;

use App\Enums\ResourceHub;
use App\Enums\ResourceType;
use App\Models\Course;
use App\Models\LearningResource;
use Illuminate\Contracts\View\View;

class CourseController extends PublicController
{
    public function index(): View
    {
        $courses = Course::query()
            ->active()
            ->with('stream')
            ->withCount([
                'learningResources as published_resources_count' => fn ($query) => $query->published(),
            ])
            ->orderBy('name')
            ->paginate(24);

        $title = 'Freshman courses';
        $description = 'Browse freshman courses with modules, notes, past exams, and practice resources for Ethiopian universities.';
        $url = route('courses.index');

        return $this->page(
            'public.courses.index',
            [
                'title' => $title,
                'description' => $description,
                'canonical' => $url,
            ],
            [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Courses', 'url' => $url],
            ],
            $this->jsonLd->forPage('courses', $title, $description, $url, [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Courses', 'url' => $url],
            ]),
            ['courses' => $courses],
        );
    }

    public function show(Course $course): View
    {
        abort_unless($course->is_active, 404);

        $course->load('stream');

        $resources = LearningResource::query()
            ->published()
            ->whereBelongsTo($course)
            ->with(['university', 'stream'])
            ->orderByDesc('id')
            ->get();

        $counts = [
            'modules' => $resources->where('type', ResourceType::Module)->count(),
            'notes' => $resources->whereIn('type', [ResourceType::LectureNotes, ResourceType::Summary])->count(),
            'exams' => $resources->where('type', ResourceType::PastExam)->count(),
            'practice' => $resources->where('type', ResourceType::PracticeQuestion)->count(),
        ];

        $title = $course->seoTitle('Freshman '.$course->name.' resources');
        $description = $course->seoDescription(
            'Modules, notes, past exams, and practice for freshman '.$course->name.'.'
        );
        $url = route('courses.show', $course);

        return $this->page(
            'public.courses.show',
            [
                'title' => $title,
                'description' => $description,
                'canonical' => $url,
                'ogImage' => $course->og_image,
                'indexable' => $course->shouldIndex(),
            ],
            [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Courses', 'url' => route('courses.index')],
                ['name' => $course->name, 'url' => $url],
            ],
            $this->jsonLd->forPage(
                'course',
                $title,
                $description,
                $url,
                [
                    ['name' => 'Home', 'url' => route('home')],
                    ['name' => 'Courses', 'url' => route('courses.index')],
                    ['name' => $course->name, 'url' => $url],
                ],
                course: $course,
            ),
            [
                'course' => $course,
                'resources' => $resources,
                'counts' => $counts,
                'hubs' => ResourceHub::cases(),
            ],
        );
    }
}
