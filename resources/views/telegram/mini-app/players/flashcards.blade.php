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
    @if ($payload)
        <x-study.flashcards
            :payload="$payload"
            :title="$resource->title"
            :resource="$resource"
            :is-bookmarked="$isBookmarked ?? false"
            :copy="$copy"
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
