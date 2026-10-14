@props([
    'chunks' => [],
    'course' => null,
    'copy',
    'pdfs' => [],
])

<x-telegram.mini-app.pdf-viewer :pdfs="$pdfs" class="-my-3" />

@if ($chunks !== [])
    <div class="space-y-4 whitespace-pre-wrap px-4 pt-4 text-sm leading-relaxed text-text-primary">
        @foreach ($chunks as $chunk)
            <div class="rounded-2xl border border-border-light bg-surface-gray/40 px-4 py-3">{!! nl2br($chunk) !!}</div>
        @endforeach
    </div>
@endif
