<?php

namespace App\Http\Controllers\Public;

use App\Models\LearningResource;
use App\Models\University;
use Illuminate\Contracts\View\View;

class UniversityController extends PublicController
{
    public function index(): View
    {
        $universities = University::query()
            ->active()
            ->withCount([
                'learningResources as published_resources_count' => fn ($query) => $query->published(),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $title = 'Ethiopian universities';
        $description = 'Browse Ethiopian university profiles with freshman resources, courses, modules, and past exams.';
        $url = route('universities.index');

        return $this->page(
            'public.universities.index',
            [
                'title' => $title,
                'description' => $description,
                'canonical' => $url,
            ],
            [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Universities', 'url' => $url],
            ],
            $this->jsonLd->forPage('universities', $title, $description, $url, [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Universities', 'url' => $url],
            ]),
            ['universities' => $universities],
        );
    }

    public function show(University $university): View
    {
        abort_unless($university->is_active, 404);

        $university->loadCount([
            'learningResources as published_resources_count' => fn ($query) => $query->published(),
        ]);

        $resources = LearningResource::query()
            ->published()
            ->whereBelongsTo($university)
            ->with(['course', 'stream'])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $courses = LearningResource::query()
            ->published()
            ->whereBelongsTo($university)
            ->with('course.stream')
            ->get()
            ->pluck('course')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        $title = $university->seoTitle($university->name.' freshman resources');
        $description = $university->seoDescription(
            'Freshman modules, notes, and past exams for '.$university->name.'.'
        );
        $url = route('universities.show', $university);

        return $this->page(
            'public.universities.show',
            [
                'title' => $title,
                'description' => $description,
                'canonical' => $url,
                'ogImage' => $university->og_image,
                'indexable' => $university->shouldIndex(),
            ],
            [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Universities', 'url' => route('universities.index')],
                ['name' => $university->name, 'url' => $url],
            ],
            $this->jsonLd->forPage(
                'university',
                $title,
                $description,
                $url,
                [
                    ['name' => 'Home', 'url' => route('home')],
                    ['name' => 'Universities', 'url' => route('universities.index')],
                    ['name' => $university->name, 'url' => $url],
                ],
                university: $university,
            ),
            [
                'university' => $university,
                'resources' => $resources,
                'courses' => $courses,
            ],
        );
    }
}
