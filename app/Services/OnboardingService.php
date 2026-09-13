<?php

namespace App\Services;

use App\Enums\OnboardingStep;
use App\Models\Course;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Support\Collection;

class OnboardingService
{
    /**
     * @return Collection<int, Course>
     */
    public function recommendCourses(Stream $stream, ?int $universityId = null): Collection
    {
        $query = Course::query()
            ->active()
            ->where('stream_id', $stream->id)
            ->withCount(['users' => function ($builder) use ($universityId): void {
                if ($universityId !== null) {
                    $builder->where('university_id', $universityId);
                }
            }])
            ->orderByDesc('users_count')
            ->orderBy('name');

        return $query->limit(8)->get();
    }

    /**
     * @param  array<int>  $courseIds
     */
    public function syncCourses(User $user, array $courseIds): void
    {
        $validIds = Course::query()
            ->active()
            ->when($user->stream_id, fn ($q) => $q->where('stream_id', $user->stream_id))
            ->whereIn('id', $courseIds)
            ->pluck('id')
            ->all();

        $user->courses()->sync($validIds);
    }

    public function complete(User $user): void
    {
        $user->update(['onboarding_step' => OnboardingStep::Complete]);
    }
}
