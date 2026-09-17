<x-telegram.mini-app.player-layout :copy="$copy" :title="$resource->title" :back-url="$backUrl">
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
