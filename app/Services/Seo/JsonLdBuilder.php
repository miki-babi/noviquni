<?php

namespace App\Services\Seo;

use App\Models\Course;
use App\Models\LearningResource;
use App\Models\University;

class JsonLdBuilder
{
    /**
     * @param  list<array{name: string, url: string}>  $crumbs
     * @return list<array<string, mixed>>
     */
    public function forPage(
        string $pageType,
        string $name,
        string $description,
        string $url,
        array $crumbs = [],
        ?Course $course = null,
        ?LearningResource $resource = null,
        ?University $university = null,
    ): array {
        $graph = [
            $this->organization(),
            $this->webPage($name, $description, $url),
        ];

        if ($crumbs !== []) {
            $graph[] = $this->breadcrumbs($crumbs);
        }

        if ($course !== null) {
            $graph[] = $this->course($course, $description, $url);
        }

        if ($resource !== null) {
            $graph[] = $this->learningResource($resource, $url);
        }

        if ($university !== null) {
            $graph[] = $this->educationalOrganization($university);
        }

        return $graph;
    }

    /**
     * @return array<string, mixed>
     */
    public function organization(): array
    {
        return [
            '@type' => 'Organization',
            'name' => config('app.name'),
            'url' => url('/'),
            'description' => 'The digital companion for Ethiopian university students.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function webPage(string $name, string $description, string $url): array
    {
        return [
            '@type' => 'WebPage',
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => config('app.name'),
                'url' => url('/'),
            ],
        ];
    }

    /**
     * @param  list<array{name: string, url: string}>  $crumbs
     * @return array<string, mixed>
     */
    public function breadcrumbs(array $crumbs): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)->values()->map(
                fn (array $crumb, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $crumb['name'],
                    'item' => $crumb['url'],
                ],
            )->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function course(Course $course, string $description, string $url): array
    {
        return [
            '@type' => 'Course',
            'name' => $course->name,
            'description' => $description !== '' ? $description : 'Freshman '.$course->name.' resources for Ethiopian university students.',
            'url' => $url,
            'provider' => [
                '@type' => 'Organization',
                'name' => config('app.name'),
                'url' => url('/'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function learningResource(LearningResource $resource, string $url): array
    {
        return [
            '@type' => 'LearningResource',
            'name' => $resource->title,
            'description' => $resource->seoDescription(),
            'url' => $url,
            'learningResourceType' => $resource->type->label(),
            'isAccessibleForFree' => ! $resource->is_premium,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function educationalOrganization(University $university): array
    {
        $data = [
            '@type' => 'EducationalOrganization',
            'name' => $university->name,
            'url' => route('universities.show', $university),
        ];

        if (filled($university->website)) {
            $data['sameAs'] = $university->website;
        }

        if (filled($university->location)) {
            $data['address'] = [
                '@type' => 'PostalAddress',
                'addressLocality' => $university->location,
                'addressCountry' => 'ET',
            ];
        }

        return $data;
    }
}
