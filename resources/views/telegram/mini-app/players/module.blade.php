<x-telegram.mini-app.player-layout :copy="$copy" :title="$resource->title" :back-url="$backUrl" :next-url="$nextUrl ?? null" :next-label="$nextLabel ?? null" :resource="$resource" :is-bookmarked="$isBookmarked ?? false">
    <div class="space-y-5">
        @if ($course)
            <p class="text-sm text-text-secondary">{{ $course->name }}</p>
        @endif

        <x-telegram.mini-app.player-reader
            :chunks="$chunks"
            :show-quiz-nudge="$showQuizNudge"
            :course="$course"
            :copy="$copy"
        />
    </div>
</x-telegram.mini-app.player-layout>
