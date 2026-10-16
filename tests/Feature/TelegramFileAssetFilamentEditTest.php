<?php

use App\Filament\Resources\TelegramFileAssets\Pages\CreateTelegramFileAsset;
use App\Filament\Resources\TelegramFileAssets\Pages\EditTelegramFileAsset;
use App\Models\TelegramFileAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.bot_username' => 'noviquni_bot',
    ]);
});

it('allows admins to edit the file_id of an existing telegram file asset', function () {
    $admin = User::factory()->admin()->create();
    $asset = TelegramFileAsset::factory()->create([
        'file_id' => 'old-file-id',
        'file_unique_id' => 'old-unique-id',
    ]);

    $this->actingAs($admin);

    Livewire::test(EditTelegramFileAsset::class, ['record' => $asset->getRouteKey()])
        ->assertFormSet([
            'file_id' => 'old-file-id',
            'file_unique_id' => 'old-unique-id',
        ])
        ->fillForm([
            'file_id' => 'new-file-id',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($asset->fresh())->file_id->toBe('new-file-id')
        ->and($asset->fresh())->file_unique_id->toBe('old-unique-id');
});

it('allows admins to create a new telegram file asset', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test(CreateTelegramFileAsset::class)
        ->fillForm([
            'file_id' => 'new-file-id',
            'file_unique_id' => 'new-unique-id',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    $asset = TelegramFileAsset::query()->where('file_unique_id', 'new-unique-id')->first();

    expect($asset)->not->toBeNull()
        ->and($asset->file_id)->toBe('new-file-id');
});

it('prevents creating a telegram file asset with a duplicate file_unique_id', function () {
    $admin = User::factory()->admin()->create();

    TelegramFileAsset::factory()->create([
        'file_unique_id' => 'existing-unique-id',
    ]);

    $this->actingAs($admin);

    Livewire::test(CreateTelegramFileAsset::class)
        ->fillForm([
            'file_id' => 'another-file-id',
            'file_unique_id' => 'existing-unique-id',
        ])
        ->call('create')
        ->assertHasFormErrors(['file_unique_id']);

    expect(TelegramFileAsset::query()->where('file_id', 'another-file-id')->exists())->toBeFalse();
});
