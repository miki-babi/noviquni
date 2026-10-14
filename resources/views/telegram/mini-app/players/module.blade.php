<x-telegram.mini-app.player-layout :copy="$copy" :title="$resource->title" :back-url="$backUrl" :resource="$resource" :is-bookmarked="$isBookmarked ?? false">
    <x-telegram.mini-app.player-reader
        :chunks="$chunks"
        :course="$course"
        :copy="$copy"
        :pdfs="$pdfs ?? []"
    />
</x-telegram.mini-app.player-layout>
