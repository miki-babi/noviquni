<x-telegram.mini-app.player-layout :copy="$copy" :title="$resource->title" :back-url="$backUrl">
    <div class="space-y-5">
        @if ($course)
            <p class="text-sm text-text-secondary">{{ $course->name }}</p>
        @endif

        <x-study.flashcards :payload="$payload" />
    </div>
</x-telegram.mini-app.player-layout>
