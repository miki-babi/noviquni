<?php

namespace App\Services;

use App\Enums\StudyPlanType;
use App\Models\Course;
use App\Models\StudyPlan;
use App\Models\User;
use Illuminate\Support\Collection;

class StudyPlanService
{
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
            ->filter(fn (StudyPlan $plan): bool => $plan->canAccess($user))
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
