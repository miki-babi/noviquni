<?php

use App\Enums\OnboardingStep;
use App\Enums\StudyPlanType;
use App\Models\Course;
use App\Models\Stream;
use App\Models\StudyPlan;
use App\Models\StudyPlanItem;
use App\Models\User;
use App\Services\CoursePathService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
});

it('shows bait checklists to free students and hides week plans', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id, 'name' => 'Math']);
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $bait = StudyPlan::factory()->published()->baitChecklist()->create([
        'course_id' => $course->id,
        'title' => 'Week-1 checklist',
    ]);
    StudyPlanItem::factory()->create([
        'study_plan_id' => $bait->id,
        'label' => 'Read bait notes',
        'sort_order' => 1,
    ]);

    StudyPlan::factory()->published()->create([
        'course_id' => $course->id,
        'type' => StudyPlanType::Week,
        'title' => 'Week 2 plan',
        'is_premium' => true,
    ]);

    $plans = app(CoursePathService::class)->plansForUser($user, $course);

    expect($plans)->toHaveCount(1)
        ->and($plans->first()->title)->toBe('Week-1 checklist');

    $this->actingAs($user)
        ->get(route('tg.courses.show', $course))
        ->assertOk()
        ->assertSee('Week-1 checklist')
        ->assertSee('Read bait notes')
        ->assertDontSee('Week 2 plan');
});

it('shows week and exam-sprint plans to premium students', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);
    $user = User::factory()->student()->premium()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    StudyPlan::factory()->published()->baitChecklist()->create([
        'course_id' => $course->id,
        'title' => 'Week-1 checklist',
    ]);
    StudyPlan::factory()->published()->create([
        'course_id' => $course->id,
        'type' => StudyPlanType::Week,
        'title' => 'Week 2 plan',
    ]);
    StudyPlan::factory()->published()->examSprint()->create([
        'course_id' => $course->id,
        'title' => 'Final sprint',
    ]);

    $plans = app(CoursePathService::class)->plansForUser($user, $course);

    expect($plans->pluck('title')->all())->toBe([
        'Week-1 checklist',
        'Week 2 plan',
        'Final sprint',
    ]);
});

it('forces bait checklists to be non-premium on save', function () {
    $plan = StudyPlan::factory()->create([
        'type' => StudyPlanType::BaitChecklist,
        'is_premium' => true,
    ]);

    expect($plan->fresh()->is_premium)->toBeFalse();
});
