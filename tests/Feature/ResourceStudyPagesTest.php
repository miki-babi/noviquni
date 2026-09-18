<?php

use App\Models\LearningResource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('never renders interactive study UIs on public resource pages', function (string $state, string $hiddenText, bool $bait) {
    config(['services.telegram.bot_username' => 'noviquni_bot']);

    $resource = LearningResource::factory()->published()->{$state}()->create([
        'title' => 'Anthropology Resource',
        'slug' => 'anthropology-'.str($state)->slug(),
        'is_bait' => $bait,
    ]);

    $response = $this->get(route('resources.show', $resource))
        ->assertOk()
        ->assertSee('Anthropology Resource', false)
        ->assertSee('https://t.me/noviquni_bot?start=resource_'.$resource->id, false)
        ->assertSee('study materials open inside Telegram', false)
        ->assertDontSee($hiddenText, false)
        ->assertDontSee('Study on the web', false)
        ->assertDontSee('Also open in Telegram', false);

    if ($bait) {
        $response->assertSee('Grab free Week-1 bait in Telegram', false);
    } else {
        $response->assertSee('Open in Telegram to access', false);
    }
})->with([
    'notes' => ['notes', 'Anthropology studies humankind across time and space.', true],
    'quiz' => ['quiz', 'What are the Greek roots of anthropology?', true],
    'exam' => ['exam', 'Which statement best describes anthropology?', true],
    'flashcards' => ['flashcards', 'What does anthropos mean?', false],
]);

it('hides premium quiz payload from the public page and keeps the telegram cta', function () {
    config(['services.telegram.bot_username' => 'noviquni_bot']);

    $resource = LearningResource::factory()->published()->premium()->quiz()->create([
        'title' => 'Premium Anthropology Quiz',
        'slug' => 'premium-anthropology-quiz',
    ]);

    $this->get(route('resources.show', $resource))
        ->assertOk()
        ->assertSee('Premium Anthropology Quiz')
        ->assertSee('Premium via Telegram', false)
        ->assertSee('study materials open inside Telegram', false)
        ->assertSee('https://t.me/noviquni_bot?start=resource_'.$resource->id, false)
        ->assertDontSee('What are the Greek roots of anthropology?', false)
        ->assertDontSee('Anthropos and logos', false)
        ->assertDontSee('Anthropos means human being.', false)
        ->assertDontSee('Practice quiz', false)
        ->assertDontSee('Study on the web', false);
});

it('returns 404 for unpublished study resources', function () {
    $resource = LearningResource::factory()->quiz()->create([
        'title' => 'Draft Quiz',
        'slug' => 'draft-quiz',
        'is_published' => false,
    ]);

    $this->get(route('resources.show', $resource))
        ->assertNotFound();
});

it('keeps telegram landing for free resources without study payload', function () {
    config(['services.telegram.bot_username' => 'noviquni_bot']);

    $resource = LearningResource::factory()->published()->create([
        'title' => 'Module Pack',
        'slug' => 'module-pack',
        'description' => 'A classic module without generated content.',
        'content' => null,
        'generation_kind' => null,
    ]);

    $this->get(route('resources.show', $resource))
        ->assertOk()
        ->assertSee('Module Pack')
        ->assertSee('study materials open inside Telegram', false)
        ->assertDontSee('Practice quiz', false);
});
