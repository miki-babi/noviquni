<?php

use App\Models\LearningResource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders interactive study UIs for free published college resources', function (string $state, string $visibleText, string $heading) {
    config(['services.telegram.bot_username' => 'noviquni_bot']);

    $resource = LearningResource::factory()->published()->{$state}()->create([
        'title' => 'Anthropology '.$heading,
        'slug' => 'anthropology-'.str($state)->slug(),
    ]);

    $this->get(route('resources.show', $resource))
        ->assertOk()
        ->assertSee($heading, false)
        ->assertSee($visibleText, false)
        ->assertSee('Study on the web', false)
        ->assertSee('Also open in Telegram', false)
        ->assertDontSee('full generated content is delivered inside Telegram', false);
})->with([
    'notes' => ['notes', 'Anthropology studies humankind across time and space.', 'Study notes'],
    'quiz' => ['quiz', 'What are the Greek roots of anthropology?', 'Practice quiz'],
    'exam' => ['exam', 'Which statement best describes anthropology?', 'Practice exam'],
    'flashcards' => ['flashcards', 'What does anthropos mean?', 'Flashcards'],
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
        ->assertSee('full generated content is delivered inside Telegram', false)
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
        ->assertSee('full generated content is delivered inside Telegram', false)
        ->assertDontSee('Practice quiz', false);
});
