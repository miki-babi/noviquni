<?php

use App\Enums\ResourceType;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\University;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the public home page with telegram bait cta', function () {
    config(['services.telegram.bot_username' => 'noviquni_bot']);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee('digital companion for Ethiopian university students', false);
    $response->assertSee('https://t.me/noviquni_bot?start=bait', false);
    $response->assertSee('Grab free Week-1 / course bait', false);
    $response->assertSee('<link rel="canonical"', false);
    $response->assertSee('application/ld+json', false);
    $response->assertSee('organized freshman study system', false);
});

it('shows active universities and returns 404 for inactive ones', function () {
    $active = University::factory()->create([
        'name' => 'Addis Ababa University',
        'slug' => 'addis-ababa-university',
        'description' => 'Flagship Ethiopian university.',
        'is_active' => true,
    ]);
    $inactive = University::factory()->create([
        'slug' => 'inactive-uni',
        'is_active' => false,
    ]);

    $this->get(route('universities.index'))
        ->assertOk()
        ->assertSee('Addis Ababa University');

    $this->get(route('universities.show', $active))
        ->assertOk()
        ->assertSee('Flagship Ethiopian university.')
        ->assertSee('At a glance');

    $this->get(route('universities.show', $inactive))
        ->assertNotFound();
});

it('shows course pages with resource counts and hub links', function () {
    $stream = Stream::factory()->create(['name' => 'Natural', 'slug' => 'natural']);
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Mathematics',
        'slug' => 'mathematics',
    ]);

    LearningResource::factory()->published()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::Module,
        'title' => 'Mathematics Module 1',
        'slug' => 'mathematics-module-1',
    ]);

    $this->get(route('courses.show', $course))
        ->assertOk()
        ->assertSee('Freshman Mathematics resources')
        ->assertSee('Mathematics Module 1')
        ->assertSee(route('hubs.course', ['exams', $course]), false);
});

it('shows published resources and hides unpublished ones', function () {
    config(['services.telegram.bot_username' => 'noviquni_bot']);

    $published = LearningResource::factory()->published()->create([
        'title' => 'Physics Notes Pack',
        'slug' => 'physics-notes-pack',
        'description' => 'Core freshman physics notes.',
        'topics' => ['Mechanics', 'Waves'],
        'type' => ResourceType::Notes,
        'content' => null,
        'generation_kind' => null,
    ]);

    $unpublished = LearningResource::factory()->create([
        'title' => 'Draft Notes',
        'slug' => 'draft-notes',
        'is_published' => false,
    ]);

    $this->get(route('resources.show', $published))
        ->assertOk()
        ->assertSee('Physics Notes Pack')
        ->assertSee('Mechanics')
        ->assertSee('https://t.me/noviquni_bot?start=resource_'.$published->id, false)
        ->assertSee('organized study path lives inside Telegram', false);

    $this->get(route('resources.show', $unpublished))
        ->assertNotFound();
});

it('renders resource hubs and course-filtered hubs', function () {
    $course = Course::factory()->create([
        'name' => 'Chemistry',
        'slug' => 'chemistry',
    ]);

    LearningResource::factory()->published()->create([
        'course_id' => $course->id,
        'stream_id' => $course->stream_id,
        'type' => ResourceType::MidExam,
        'title' => 'Chemistry Mid Exam',
        'slug' => 'chemistry-mid-exam',
    ]);

    $this->get(route('hubs.show', 'exams'))
        ->assertOk()
        ->assertSee('Freshman Exams')
        ->assertSee('Chemistry Mid Exam');

    $this->get(route('hubs.course', ['exams', $course]))
        ->assertOk()
        ->assertSee('Freshman Chemistry Exams')
        ->assertSee('Chemistry Mid Exam');
});

it('renders stream pages for active streams', function () {
    $stream = Stream::factory()->create([
        'name' => 'Social',
        'slug' => 'social',
        'is_active' => true,
    ]);

    Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Economics',
        'slug' => 'economics',
    ]);

    $this->get(route('streams.show', $stream))
        ->assertOk()
        ->assertSee('Social stream')
        ->assertSee('Economics');
});

it('uses noindex when a page is marked not indexable', function () {
    $course = Course::factory()->create([
        'name' => 'Hidden Course',
        'slug' => 'hidden-course',
        'is_indexable' => false,
    ]);

    $this->get(route('courses.show', $course))
        ->assertOk()
        ->assertSee('name="robots" content="noindex,follow"', false);
});
