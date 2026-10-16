<x-telegram.mini-app.player-layout
    :copy="$copy"
    :title="$resource->title"
    :back-url="$backUrl"
    :resource="$resource"
    :is-bookmarked="$isBookmarked ?? false"
    :show-bookmark="false"
    :show-bot-footer="true"
    :show-menu="false"
>
    <x-telegram.mini-app.pdf-viewer :pdfs="$pdfs ?? []" class="-my-3" />

    @if ($payload)
        <x-study.exam
            :payload="$payload"
            :resource="$resource"
            :is-bookmarked="$isBookmarked ?? false"
            :copy="$copy"
            :result-url="route('tg.play.exam.result', $resource)"
            :report-url="route('tg.play.question-report')"
            report-source="exam"
            :learning-resource-id="$resource->id"
        />
    @else
        <div class="space-y-5 px-4">
            @if ($course)
                <p class="text-sm text-text-secondary">{{ $course->name }}</p>
            @endif

            <x-telegram.mini-app.player-reader :chunks="$chunks" :copy="$copy" />
        </div>
    @endif
</x-telegram.mini-app.player-layout>
