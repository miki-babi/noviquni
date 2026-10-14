<x-telegram.mini-app.player-layout :copy="$copy" :title="$resource->title" :back-url="$backUrl" :next-url="$nextUrl ?? null" :next-label="$nextLabel ?? null" :resource="$resource" :is-bookmarked="$isBookmarked ?? false">
    <x-telegram.mini-app.player-reader :chunks="$chunks" :copy="$copy" :pdfs="$pdfs ?? []" />
</x-telegram.mini-app.player-layout>
