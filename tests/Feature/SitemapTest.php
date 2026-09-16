<?php

use App\Enums\ResourceType;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\University;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes published indexable urls and excludes unpublished resources', function () {
    config(['services.telegram.bot_username' => 'noviquni_bot']);

    $university = University::factory()->create([
        'slug' => 'jimma-university',
        'is_active' => true,
        'is_indexable' => true,
    ]);

    $course = Course::factory()->create([
        'slug' => 'physics',
        'is_active' => true,
        'is_indexable' => true,
    ]);

    $published = LearningResource::factory()->published()->create([
        'course_id' => $course->id,
        'stream_id' => $course->stream_id,
        'slug' => 'physics-module-1',
        'type' => ResourceType::Module,
        'is_indexable' => true,
    ]);

    $unpublished = LearningResource::factory()->create([
        'course_id' => $course->id,
        'stream_id' => $course->stream_id,
        'slug' => 'physics-draft',
        'is_published' => false,
        'is_indexable' => true,
    ]);

    $noindex = LearningResource::factory()->published()->create([
        'course_id' => $course->id,
        'stream_id' => $course->stream_id,
        'slug' => 'physics-private',
        'is_indexable' => false,
    ]);

    $response = $this->get(route('sitemap'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml');
    $response->assertSee(route('home'), false);
    $response->assertSee(route('universities.show', $university), false);
    $response->assertSee(route('courses.show', $course), false);
    $response->assertSee(route('resources.show', $published), false);
    $response->assertSee(route('hubs.course', ['modules', $course]), false);
    $response->assertDontSee(route('resources.show', $unpublished), false);
    $response->assertDontSee(route('resources.show', $noindex), false);
});
