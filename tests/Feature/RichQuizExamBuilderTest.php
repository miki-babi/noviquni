<?php

use App\Enums\CollegeResourceKind;
use App\Enums\OnboardingStep;
use App\Enums\ResourceType;
use App\Filament\Resources\Learnings\Pages\CreateLearning;
use App\Filament\Resources\Learnings\Pages\EditLearning;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\User;
use App\Services\College\TelegramResourceFormatter;
use App\Services\SettingsService;
use App\Support\StudyRichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => '123456:TEST_TOKEN',
        'services.telegram.bot_username' => 'noviquni_bot',
        'app.url' => 'https://noviquni.test',
    ]);
});

it('persists rich quiz questions and generation kind from the learning create form', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create([
        'stream_id' => null,
    ]);

    $this->actingAs($admin);

    Livewire::test(CreateLearning::class)
        ->fillForm([
            'type' => ResourceType::Quiz->value,
            'title' => 'Rich anthropology quiz',
            'slug' => 'rich-anthropology-quiz',
            'course_id' => $course->id,
            'sort_order' => 0,
            'is_premium' => false,
            'is_published' => true,
            'is_indexable' => true,
            'file_source' => 'telegram',
            'telegram_file_ids' => [],
        ])
        ->fillForm([
            'quiz_questions' => [
                [
                    'question' => StudyRichText::forEditor('<p>What are the <strong>Greek roots</strong> of anthropology?</p>'),
                    'options' => [
                        ['body' => StudyRichText::forEditor('Wrong A')],
                        ['body' => StudyRichText::forEditor('Anthropos and logos')],
                        ['body' => StudyRichText::forEditor('Wrong C')],
                        ['body' => StudyRichText::forEditor('Wrong D')],
                    ],
                    'answerIndex' => 1,
                    'explanation' => StudyRichText::forEditor('<p>From <em>anthropos</em> (human) and <em>logos</em> (study).</p>'),
                ],
                [
                    'question' => StudyRichText::forEditor('Which option includes an image path?'),
                    'options' => [
                        ['body' => StudyRichText::forEditor('None')],
                        ['body' => StudyRichText::forEditor('<p><img src="/storage/learning-resources/quiz-content/1/diagram.png" alt="diagram"></p>')],
                    ],
                    'answerIndex' => 1,
                    'explanation' => null,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    $resource = LearningResource::query()->where('slug', 'rich-anthropology-quiz')->first();

    expect($resource)->not->toBeNull()
        ->and($resource->type)->toBe(ResourceType::Quiz)
        ->and($resource->generation_kind)->toBe(CollegeResourceKind::Quiz)
        ->and($resource->content)->toBeArray()
        ->and($resource->content['kind'] ?? null)->toBe(CollegeResourceKind::Quiz->value)
        ->and($resource->content['payload']['questions'] ?? [])->toHaveCount(2)
        ->and($resource->content['payload']['questions'][0]['answerIndex'])->toBe(1)
        ->and(StudyRichText::toPlain($resource->content['payload']['questions'][0]['question']))->toContain('Greek roots')
        ->and(StudyRichText::toHtml($resource->content['payload']['questions'][1]['options'][1]))->toContain('diagram.png');
});

it('updates practice exam content from the rich questions builder', function () {
    $admin = User::factory()->admin()->create();
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $resource = LearningResource::factory()->exam()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Editable rich exam',
    ]);

    $this->actingAs($admin);

    Livewire::test(EditLearning::class, ['record' => $resource->getRouteKey()])
        ->assertFormSet([
            'type' => ResourceType::PracticeExams->value,
        ])
        ->fillForm([
            'exam_title' => 'Part A — Updated',
            'exam_instructions' => 'Choose carefully.',
            'quiz_questions' => [
                [
                    'question' => StudyRichText::forEditor('Edited MCQ about anthropology?'),
                    'options' => [
                        ['body' => StudyRichText::forEditor('One')],
                        ['body' => StudyRichText::forEditor('Two')],
                        ['body' => StudyRichText::forEditor('Three')],
                        ['body' => StudyRichText::forEditor('Four')],
                    ],
                    'answerIndex' => 2,
                    'explanation' => StudyRichText::forEditor('Because.'),
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $resource->refresh();

    expect($resource->generation_kind)->toBe(CollegeResourceKind::Exam)
        ->and($resource->content['payload']['title'])->toBe('Part A — Updated')
        ->and($resource->content['payload']['instructions'])->toBe('Choose carefully.')
        ->and($resource->content['payload']['questions'])->toHaveCount(1)
        ->and(StudyRichText::toPlain($resource->content['payload']['questions'][0]['question']))->toContain('Edited MCQ')
        ->and($resource->playerPayload()['questions'][0]['answerIndex'])->toBe(2);
});

it('renders rich html in the quiz player and keeps legacy plain text playable', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $richQuiz = LearningResource::factory()->published()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Rich HTML quiz',
        'content' => [
            'kind' => CollegeResourceKind::Quiz->value,
            'payload' => [
                'questions' => [
                    [
                        'question' => '<p>Pick the <strong>correct</strong> answer</p>',
                        'options' => [
                            '<p>Wrong</p>',
                            '<p><img src="/storage/learning-resources/quiz-content/9/chart.png" alt="chart"></p>',
                        ],
                        'answerIndex' => 1,
                        'explanation' => '<p>See the <em>chart</em>.</p>',
                    ],
                ],
            ],
        ],
    ]);

    $legacyQuiz = LearningResource::factory()->published()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Legacy plain quiz',
    ]);

    $this->actingAs($user)
        ->get(route('tg.play.quiz', $richQuiz))
        ->assertOk()
        ->assertSee('<strong>correct</strong>', false)
        ->assertSee('/storage/learning-resources/quiz-content/9/chart.png', false)
        ->assertSee('<em>chart</em>', false);

    $this->actingAs($user)
        ->get(route('tg.play.quiz', $legacyQuiz))
        ->assertOk()
        ->assertSee('What are the Greek roots of anthropology?', false)
        ->assertSee('Anthropos and logos', false);
});

it('strips rich quiz fields to plain text for telegram delivery', function () {
    $resource = LearningResource::factory()->quiz()->create([
        'title' => 'Telegram rich quiz',
        'content' => [
            'kind' => CollegeResourceKind::Quiz->value,
            'payload' => [
                'questions' => [
                    [
                        'question' => '<p>What is <strong>anthropology</strong>?</p>',
                        'options' => [
                            '<p>Option <em>A</em></p>',
                            '<p>Option B</p>',
                        ],
                        'answerIndex' => 0,
                        'explanation' => '<p>Because humans.</p>',
                    ],
                ],
            ],
        ],
    ]);

    $chunks = app(TelegramResourceFormatter::class)->format($resource);
    $body = implode("\n", $chunks);

    expect($body)->toContain('What is anthropology?')
        ->and($body)->toContain('Option A')
        ->and($body)->toContain('Because humans.')
        ->and($body)->not->toContain('<strong>')
        ->and($body)->not->toContain('<em>');
});

it('converts legacy plain strings and html through StudyRichText', function () {
    expect(StudyRichText::toHtml('Plain legacy stem'))->toBe('Plain legacy stem')
        ->and(StudyRichText::toHtml('<p>Hello <strong>world</strong></p>'))->toContain('<strong>world</strong>')
        ->and(StudyRichText::toPlain('<p>Hello <strong>world</strong></p>'))->toBe('Hello world')
        ->and(StudyRichText::isEmpty('<p></p>'))->toBeTrue()
        ->and(StudyRichText::isEmpty('<p>Hi</p>'))->toBeFalse()
        ->and(StudyRichText::isEmpty('<p><img src="/storage/diagram.png" alt="diagram"></p>'))->toBeFalse();
});
