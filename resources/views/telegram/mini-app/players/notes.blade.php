<x-telegram.mini-app.player-layout :copy="$copy" :title="$resource->title" :back-url="$backUrl" :next-url="$nextUrl ?? null" :next-label="$nextLabel ?? null" :resource="$resource" :is-bookmarked="$isBookmarked ?? false">
    <div class="space-y-5">
        @if ($course)
            <p class="text-sm text-text-secondary">{{ $course->name }}</p>
        @endif

        @if ($payload)
            <x-study.notes :payload="$payload" />

            @if ($showQuizNudge && $course)
                <div class="space-y-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4">
                    <p class="whitespace-pre-line text-sm text-emerald-900">{{ $copy->get('quiz_nudge.text', ['course' => $course->name]) }}</p>
                    <a
                        href="{{ route('tg.courses.hub', ['course' => $course, 'hub' => 'practice']) }}"
                        class="inline-flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white"
                    >
                        {{ $copy->get('quiz_nudge.button') }}
                    </a>
                </div>
            @endif
        @else
            <x-telegram.mini-app.player-reader
                :chunks="$chunks"
                :show-quiz-nudge="$showQuizNudge"
                :course="$course"
                :copy="$copy"
            />
        @endif
    </div>
</x-telegram.mini-app.player-layout>
