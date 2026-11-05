<?php

namespace App\Services;

use App\Enums\OnboardingStep;
use App\Enums\UserEventName;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Collection;

class OnboardingService
{
    public function __construct(
        public UserEventService $userEvents,
    ) {}

    /**
     * @return Collection<int, Course>
     */
    public function recommendCourses(): Collection
    {
        return Course::query()
            ->active()
            ->withCount('users')
            ->orderByDesc('users_count')
            ->orderBy('name')
            ->get();
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

    /**
     * Enroll the three GAT subjects (maths, reading/writing, analytical reasoning).
     *
     * @return list<int> Enrolled course ids
     */
    public function syncGatSubjectCourses(User $user): array
    {
        $ids = $this->gatSubjectCourseIds();

        if ($ids === []) {
            return [];
        }

        $user->courses()->syncWithoutDetaching($ids);

        return $ids;
    }

    /**
     * @return list<int>
     */
    public function gatSubjectCourseIds(): array
    {
        $ids = [];

        foreach ($this->gatSubjectSlugGroups() as $aliases) {
            $courseId = Course::query()
                ->active()
                ->whereIn('slug', $aliases)
                ->orderBy('id')
                ->value('id');

            if ($courseId !== null) {
                $ids[] = (int) $courseId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<list<string>>
     */
    public function gatSubjectSlugGroups(): array
    {
        return [
            ['maths'],
            ['reading-writing', 'reading-and-writing', 'readingwriting'],
            ['analytical-reasoning', 'analyticalreasoning'],
        ];
    }

    public function complete(User $user): void
    {
        if ($user->onboarding_step === OnboardingStep::Complete) {
            return;
        }

        $user->update(['onboarding_step' => OnboardingStep::Complete]);

        $this->userEvents->log($user, UserEventName::OnboardingDone, array_filter([
            'department' => $user->department_name,
        ], fn (mixed $value): bool => $value !== null && $value !== ''));
    }
}
