<?php

namespace App\Observers;

use App\Jobs\SendTelegramMessageJob;
use App\Models\LearningResource;
use App\Models\User;

class LearningResourceObserver
{
    public function created(LearningResource $learningResource): void
    {
        if (! $learningResource->is_published) {
            return;
        }

        $this->notifyCourseStudents($learningResource);
    }

    public function updated(LearningResource $learningResource): void
    {
        if (! $learningResource->wasChanged('is_published') || ! $learningResource->is_published) {
            return;
        }

        $this->notifyCourseStudents($learningResource);
    }

    protected function notifyCourseStudents(LearningResource $learningResource): void
    {
        User::query()
            ->students()
            ->active()
            ->where('notifications_enabled', true)
            ->whereNotNull('telegram_id')
            ->whereHas('courses', fn ($q) => $q->where('courses.id', $learningResource->course_id))
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($learningResource): void {
                foreach ($users as $user) {
                    SendTelegramMessageJob::dispatch(
                        $user->telegram_id,
                        "New resource available: <b>{$learningResource->title}</b>",
                    );
                }
            });
    }
}
