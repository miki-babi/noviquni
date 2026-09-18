@props([
    'chunks' => [],
    'course' => null,
    'copy',
])

<div class="space-y-4 whitespace-pre-wrap text-sm leading-relaxed text-text-primary">
    @foreach ($chunks as $chunk)
        <div class="rounded-2xl border border-border-light bg-surface-gray/40 px-4 py-3">{!! nl2br($chunk) !!}</div>
    @endforeach
</div>
