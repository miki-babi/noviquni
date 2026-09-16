<?php

namespace App\Http\Controllers\Public;

use App\Enums\ResourceHub;
use App\Models\Course;
use App\Models\LearningResource;
use Illuminate\Contracts\View\View;

class ResourceHubController extends PublicController
{
    public function show(string $hub, ?Course $course = null): View
    {
        $resourceHub = ResourceHub::tryFrom($hub);
        abort_unless($resourceHub instanceof ResourceHub, 404);

        if ($course !== null) {
            abort_unless($course->is_active, 404);
            $course->load('stream');
        }

        $resourcesQuery = LearningResource::query()
            ->published()
            ->whereIn('type', $resourceHub->typeValues())
            ->with(['course', 'stream', 'university'])
            ->orderByDesc('id');

        if ($course !== null) {
            $resourcesQuery->whereBelongsTo($course);
        }

        $resources = $resourcesQuery->paginate(24)->withQueryString();

        $title = $course
            ? 'Freshman '.$course->name.' '.$resourceHub->label()
            : 'Freshman '.$resourceHub->label();

        $description = $course
            ? 'Browse '.$resourceHub->label().' for freshman '.$course->name.'.'
            : 'Browse freshman '.$resourceHub->label().' for Ethiopian university students.';

        $url = $course
            ? route('hubs.course', [$resourceHub->value, $course])
            : route('hubs.show', $resourceHub->value);

        $breadcrumbs = [
            ['name' => 'Home', 'url' => route('home')],
            ['name' => $resourceHub->label(), 'url' => route('hubs.show', $resourceHub->value)],
        ];

        if ($course !== null) {
            $breadcrumbs[] = ['name' => $course->name, 'url' => $url];
        }

        return $this->page(
            'public.hubs.show',
            [
                'title' => $title,
                'description' => $description,
                'canonical' => $url,
                'indexable' => $course?->shouldIndex() ?? true,
            ],
            $breadcrumbs,
            $this->jsonLd->forPage(
                'hub',
                $title,
                $description,
                $url,
                array_map(
                    fn (array $crumb): array => [
                        'name' => $crumb['name'],
                        'url' => $crumb['url'] ?? route('home'),
                    ],
                    $breadcrumbs,
                ),
                course: $course,
            ),
            [
                'hub' => $resourceHub,
                'course' => $course,
                'resources' => $resources,
            ],
        );
    }
}
