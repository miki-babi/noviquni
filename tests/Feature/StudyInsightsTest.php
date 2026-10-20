<?php

use App\Enums\UserRole;
use App\Filament\Pages\StudyInsights;
use App\Filament\Widgets\MostActiveStudentsTable;
use App\Filament\Widgets\OpensByStreamChart;
use App\Filament\Widgets\OpensByTypeChart;
use App\Filament\Widgets\OpensByUniversityChart;
use App\Filament\Widgets\OpensTrendChart;
use App\Filament\Widgets\StudyInsightsStats;
use App\Filament\Widgets\TopStudiedCoursesTable;
use App\Filament\Widgets\TopStudiedResourcesTable;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\ResourceDownload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('loads the insights page for super admins', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/study-insights')
        ->assertOk()
        ->assertSeeLivewire(StudyInsightsStats::class)
        ->assertSeeLivewire(OpensTrendChart::class)
        ->assertSeeLivewire(TopStudiedResourcesTable::class)
        ->assertSeeLivewire(MostActiveStudentsTable::class);
});

it('blocks staff without the super admin role', function () {
    $role = Role::findOrCreate('editor', 'web');

    $staff = User::factory()->create([
        'role' => UserRole::Admin,
        'telegram_id' => null,
        'is_active' => true,
    ]);
    $staff->assignRole($role);

    $this->actingAs($staff)
        ->get('/admin/study-insights')
        ->assertForbidden();
});

it('ranks resources by opens in the period', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();

    $popular = LearningResource::factory()->create(['title' => 'Popular resource']);
    $quiet = LearningResource::factory()->create(['title' => 'Quiet resource']);

    ResourceDownload::query()->create(['user_id' => $student->id, 'learning_resource_id' => $popular->id]);
    ResourceDownload::query()->create(['user_id' => $student->id, 'learning_resource_id' => $popular->id]);
    ResourceDownload::query()->create(['user_id' => $student->id, 'learning_resource_id' => $quiet->id]);

    $this->actingAs($admin);

    Livewire::test(TopStudiedResourcesTable::class)
        ->assertCanSeeTableRecords([$popular, $quiet], inOrder: true);
});

it('aggregates opens per course and per student', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create(['name' => 'Busy Student']);

    $course = Course::factory()->create(['name' => 'Signals 101']);
    $resource = LearningResource::factory()->for($course)->create();

    ResourceDownload::query()->create(['user_id' => $student->id, 'learning_resource_id' => $resource->id]);

    $this->actingAs($admin);

    Livewire::test(TopStudiedCoursesTable::class)
        ->assertCanSeeTableRecords([$course])
        ->assertTableColumnValueSet('downloads_count', 1, $course);

    Livewire::test(MostActiveStudentsTable::class)
        ->assertCanSeeTableRecords([$student]);
});

it('keeps insight widgets off the main dashboard', function (string $widget) {
    expect($widget::isDiscovered())->toBeFalse();
})->with([
    StudyInsightsStats::class,
    OpensTrendChart::class,
    OpensByTypeChart::class,
    OpensByStreamChart::class,
    OpensByUniversityChart::class,
    TopStudiedResourcesTable::class,
    TopStudiedCoursesTable::class,
    MostActiveStudentsTable::class,
]);

it('counts opens in the trend chart data', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();
    $resource = LearningResource::factory()->create();

    ResourceDownload::query()->create(['user_id' => $student->id, 'learning_resource_id' => $resource->id]);

    $this->actingAs($admin);

    Livewire::test(OpensTrendChart::class)
        ->assertOk();
});
