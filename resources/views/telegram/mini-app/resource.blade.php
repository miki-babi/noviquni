<x-telegram.mini-app.layout :copy="$copy" :active-nav="$activeNav" :title="$resource->title">
    <div class="space-y-5">
        @if ($course)
            <p class="text-sm text-text-secondary">{{ $course->name }}</p>
        @endif

        <div class="space-y-4 whitespace-pre-wrap text-sm leading-relaxed text-text-primary">
            @foreach ($chunks as $chunk)
                <div class="rounded-2xl border border-border-light bg-surface-gray/40 px-4 py-3">{!! nl2br($chunk) !!}</div>
            @endforeach
        </div>

        @if ($course)
            <a href="{{ route('tg.courses.show', $course) }}" class="inline-flex w-full items-center justify-center rounded-2xl border border-border-light px-4 py-3 text-sm font-semibold">
                {{ $copy->get('hub.back') }}
            </a>
        @endif
    </div>
</x-telegram.mini-app.layout>
