<?php

namespace App\Services;

use App\Enums\OnboardingStep;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Collection;

class OnboardingService
{
    /**
     * @return Collection<int, Course>
     */
    public function recommendCourses(?int $universityId = null): Collection
    {
        $query = Course::query()
            ->active()
            ->withCount(['users' => function ($builder) use ($universityId): void {
                if ($universityId !== null) {
                    $builder->where('university_id', $universityId);
                }
            }])
            ->orderByDesc('users_count')
            ->orderBy('name');

        return $query->get();
    }

    /**
     * @param  array<int>  $courseIds
     */
    public function syncCourses(User $user, array $courseIds): void
    {
        $validIds = Course::query()
            ->active()
            ->whereIn('id', $courseIds)
            ->pluck('id')
            ->all();

        $user->courses()->sync($validIds);
    }

    public function syncAllActiveCourses(User $user): void
    {
        $this->syncCourses(
            $user,
            Course::query()->active()->orderBy('name')->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    public function complete(User $user): void
    {
        $user->update(['onboarding_step' => OnboardingStep::Complete]);
    }
}
