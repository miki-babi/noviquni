<x-telegram.mini-app.player-layout :copy="$copy" :title="$resource->title" :back-url="$backUrl" :next-url="$nextUrl ?? null" :next-label="$nextLabel ?? null" :resource="$resource" :is-bookmarked="$isBookmarked ?? false">
    <x-telegram.mini-app.pdf-viewer :pdfs="$pdfs ?? []" class="-my-3" />

    <div class="space-y-5 px-4 pt-4">
        @if ($course && ($pdfs ?? []) === [])
            <p class="text-sm text-text-secondary">{{ $course->name }}</p>
        @endif

        @if ($payload)
            <x-study.quiz
                :payload="$payload"
                :result-url="route('tg.play.quiz.result', $resource)"
            />
        @else
            <x-telegram.mini-app.player-reader :chunks="$chunks" :copy="$copy" />
        @endif
    </div>
</x-telegram.mini-app.player-layout>
