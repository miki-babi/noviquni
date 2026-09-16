<?php

namespace App\Http\Controllers\Public;

use App\Models\LearningResource;
use Illuminate\Contracts\View\View;

class ResourceController extends PublicController
{
    public function index(): View
    {
        $resources = LearningResource::query()
            ->published()
            ->with(['course', 'stream', 'university'])
            ->orderByDesc('id')
            ->paginate(24);

        $title = 'Learning resources';
        $description = 'Browse published freshman modules, notes, past exams, and practice sets for Ethiopian university students.';
        $url = route('resources.index');

        return $this->page(
            'public.resources.index',
            [
                'title' => $title,
                'description' => $description,
                'canonical' => $url,
            ],
            [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Resources', 'url' => $url],
            ],
            $this->jsonLd->forPage('resources', $title, $description, $url, [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Resources', 'url' => $url],
            ]),
            ['resources' => $resources],
        );
    }

    public function show(LearningResource $resource): View
    {
        abort_unless($resource->is_published, 404);

        $resource->load(['course.stream', 'stream', 'university', 'semester']);

        $related = LearningResource::query()
            ->published()
            ->where('course_id', $resource->course_id)
            ->where('id', '!=', $resource->id)
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $title = $resource->seoTitle($resource->title);
        $description = $resource->seoDescription(
            filled($resource->description)
                ? str($resource->description)->limit(160)->toString()
                : $resource->title.' for Ethiopian freshman students.'
        );
        $url = route('resources.show', $resource);

        return $this->page(
            'public.resources.show',
            [
                'title' => $title,
                'description' => $description,
                'canonical' => $url,
                'ogImage' => $resource->og_image,
                'indexable' => $resource->shouldIndex(),
            ],
            [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Resources', 'url' => route('resources.index')],
                ['name' => $resource->title, 'url' => $url],
            ],
            $this->jsonLd->forPage(
                'resource',
                $title,
                $description,
                $url,
                [
                    ['name' => 'Home', 'url' => route('home')],
                    ['name' => 'Resources', 'url' => route('resources.index')],
                    ['name' => $resource->title, 'url' => $url],
                ],
                course: $resource->course,
                resource: $resource,
            ),
            [
                'resource' => $resource,
                'related' => $related,
            ],
        );
    }
}
