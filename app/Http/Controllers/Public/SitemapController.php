<?php

namespace App\Http\Controllers\Public;

use App\Enums\ResourceHub;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\University;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect([
            ['loc' => route('home'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => route('universities.index'), 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => route('courses.index'), 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => route('resources.index'), 'changefreq' => 'daily', 'priority' => '0.8'],
        ]);

        foreach (ResourceHub::cases() as $hub) {
            $urls->push([
                'loc' => route('hubs.show', $hub->value),
                'changefreq' => 'daily',
                'priority' => '0.7',
            ]);
        }

        University::query()
            ->active()
            ->indexable()
            ->orderBy('id')
            ->each(function (University $university) use ($urls): void {
                $urls->push([
                    'loc' => route('universities.show', $university),
                    'lastmod' => optional($university->updated_at)?->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.7',
                ]);
            });

        Stream::query()
            ->active()
            ->indexable()
            ->orderBy('id')
            ->each(function (Stream $stream) use ($urls): void {
                $urls->push([
                    'loc' => route('streams.show', $stream),
                    'lastmod' => optional($stream->updated_at)?->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.6',
                ]);
            });

        Course::query()
            ->active()
            ->indexable()
            ->orderBy('id')
            ->each(function (Course $course) use ($urls): void {
                $urls->push([
                    'loc' => route('courses.show', $course),
                    'lastmod' => optional($course->updated_at)?->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ]);

                foreach (ResourceHub::cases() as $hub) {
                    $hasResources = LearningResource::query()
                        ->published()
                        ->whereBelongsTo($course)
                        ->whereIn('type', $hub->typeValues())
                        ->exists();

                    if ($hasResources) {
                        $urls->push([
                            'loc' => route('hubs.course', [$hub->value, $course]),
                            'changefreq' => 'weekly',
                            'priority' => '0.6',
                        ]);
                    }
                }
            });

        LearningResource::query()
            ->published()
            ->indexable()
            ->orderBy('id')
            ->each(function (LearningResource $resource) use ($urls): void {
                $urls->push([
                    'loc' => route('resources.show', $resource),
                    'lastmod' => optional($resource->updated_at)?->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.9',
                ]);
            });

        return response()
            ->view('public.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
