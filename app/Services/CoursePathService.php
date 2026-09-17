<?php

namespace App\Services;

use App\Enums\ResourceType;
use App\Enums\StudyPlanType;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\StudyPlan;
use App\Models\User;
use Illuminate\Support\Collection;

class CoursePathService
{
    public function __construct(public PremiumService $premium) {}

    /**
     * Ordered path steps for a course: modules (with children) then course-level exams.
     *
     * @return Collection<int, LearningResource>
     */
    public function orderedSteps(Course $course): Collection
    {
        $modules = LearningResource::query()
            ->published()
            ->where('course_id', $course->id)
            ->where('type', ResourceType::Module)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $steps = collect();

        foreach ($modules as $module) {
            $steps->push($module);

            $children = LearningResource::query()
                ->published()
                ->where('course_id', $course->id)
                ->where('module_id', $module->id)
                ->whereIn('type', [
                    ResourceType::LectureNotes,
                    ResourceType::Summary,
                    ResourceType::Worksheet,
                    ResourceType::PracticeQuestion,
                    ResourceType::Flashcards,
                ])
                ->get()
                ->sortBy(fn (LearningResource $resource): array => [
                    $resource->type->pathRank(),
                    $resource->sort_order,
                    $resource->id,
                ])
                ->values();

            foreach ($children as $child) {
                $steps->push($child);
            }
        }

        $exams = LearningResource::query()
            ->published()
            ->where('course_id', $course->id)
            ->where('type', ResourceType::PastExam)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($exams as $exam) {
            $steps->push($exam);
        }

        // Orphan bait notes/quizzes not linked to a module still appear for free path.
        $orphans = LearningResource::query()
            ->published()
            ->where('course_id', $course->id)
            ->whereNull('module_id')
            ->where('type', '!=', ResourceType::Module)
            ->where('type', '!=', ResourceType::PastExam)
            ->where('type', '!=', ResourceType::Assignment)
            ->where('type', '!=', ResourceType::Other)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (LearningResource $resource): bool => ! $steps->contains('id', $resource->id));

        return $orphans->concat($steps)->values();
    }

    /**
     * Next unpublished-open step on the path (Continue).
     */
    public function nextStep(User $user, Course $course): ?LearningResource
    {
        $openedIds = $user->downloads()
            ->whereIn(
                'learning_resource_id',
                $this->orderedSteps($course)->pluck('id'),
            )
            ->pluck('learning_resource_id')
            ->unique()
            ->all();

        foreach ($this->orderedSteps($course) as $step) {
            if (! $this->premium->canAccess($user, $step)) {
                // Surface the first locked step so Continue can show the wall.
                if ($openedIds === [] || in_array($step->id, $openedIds, true) === false) {
                    return $step;
                }

                continue;
            }

            if (! in_array($step->id, $openedIds, true)) {
                return $step;
            }
        }

        return $this->orderedSteps($course)->last();
    }

    /**
     * Next accessible sibling after the current resource (worksheet / quiz nudge).
     */
    public function nextAfter(User $user, LearningResource $resource): ?LearningResource
    {
        if ($resource->course_id === null) {
            return null;
        }

        $resource->loadMissing('course');
        $steps = $this->orderedSteps($resource->course);
        $index = $steps->search(fn (LearningResource $step): bool => $step->id === $resource->id);

        if ($index === false) {
            return null;
        }

        for ($i = $index + 1; $i < $steps->count(); $i++) {
            $candidate = $steps[$i];

            if ($this->premium->canAccess($user, $candidate)) {
                return $candidate;
            }

            // Show locked next step as nudge target (premium CTA).
            return $candidate;
        }

        return null;
    }

    /**
     * @return Collection<int, array{resource: LearningResource, locked: bool, completed: bool}>
     */
    public function pathForUser(User $user, Course $course): Collection
    {
        $openedIds = $user->downloads()
            ->whereIn('learning_resource_id', $this->orderedSteps($course)->pluck('id'))
            ->pluck('learning_resource_id')
            ->unique()
            ->all();

        return $this->orderedSteps($course)->map(fn (LearningResource $resource): array => [
            'resource' => $resource,
            'locked' => ! $this->premium->canAccess($user, $resource),
            'completed' => in_array($resource->id, $openedIds, true),
        ]);
    }

    /**
     * @return Collection<int, StudyPlan>
     */
    public function plansForUser(User $user, Course $course): Collection
    {
        return StudyPlan::query()
            ->published()
            ->where('course_id', $course->id)
            ->with(['items.learningResource'])
            ->orderByRaw("CASE type WHEN 'bait_checklist' THEN 0 WHEN 'week' THEN 1 WHEN 'exam_sprint' THEN 2 ELSE 3 END")
            ->orderBy('week_number')
            ->orderBy('id')
            ->get()
            ->filter(function (StudyPlan $plan) use ($user): bool {
                if ($plan->type === StudyPlanType::BaitChecklist) {
                    return true;
                }

                return $user->hasActivePremium();
            })
            ->values();
    }

    public function baitChecklist(Course $course): ?StudyPlan
    {
        return StudyPlan::query()
            ->published()
            ->where('course_id', $course->id)
            ->where('type', StudyPlanType::BaitChecklist)
            ->with(['items.learningResource'])
            ->first();
    }
}
