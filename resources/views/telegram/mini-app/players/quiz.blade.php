<x-telegram.mini-app.player-layout :copy="$copy" :title="$resource->title" :back-url="$backUrl" :next-url="$nextUrl ?? null" :next-label="$nextLabel ?? null" :resource="$resource" :is-bookmarked="$isBookmarked ?? false">
    <div class="space-y-5">
        @if ($course)
            <p class="text-sm text-text-secondary">{{ $course->name }}</p>
        @endif

        @if ($payload)
            <x-study.quiz :payload="$payload" />
        @else
            <x-telegram.mini-app.player-reader :chunks="$chunks" :copy="$copy" />
        @endif
    </div>
</x-telegram.mini-app.player-layout>
