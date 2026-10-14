<x-telegram.mini-app.player-layout :copy="$copy" :title="$resource->title" :back-url="$backUrl" :resource="$resource" :is-bookmarked="$isBookmarked ?? false">
    <x-telegram.mini-app.pdf-viewer :pdfs="$pdfs ?? []" class="-my-3" />

    @if ($payload || ($chunks ?? []) !== [])
        <div class="space-y-5 px-4 pt-4">
            @if ($course)
                <p class="text-sm text-text-secondary">{{ $course->name }}</p>
            @endif

            @if ($payload)
                <x-study.notes :payload="$payload" />
            @else
                <x-telegram.mini-app.player-reader
                    :chunks="$chunks"
                    :course="$course"
                    :copy="$copy"
                />
            @endif
        </div>
    @endif
</x-telegram.mini-app.player-layout>
