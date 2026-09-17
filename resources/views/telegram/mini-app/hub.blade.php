<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$title"
    :back-url="route('tg.courses.show', $course)"
>
    <p class="tg-section-title">{{ $course->name }}</p>

    @if ($resources->isEmpty())
        <p class="px-4 text-[15px] leading-relaxed tg-hint">{{ $copy->get('hub.no_resources') }}</p>
    @else
        <div class="tg-section">
            @foreach ($resources as $resource)
                <a href="{{ $resource->miniAppUrl() }}" class="tg-cell">
                    <span class="tg-cell-body">
                        <span class="tg-cell-title">{{ $resource->title }}</span>
                    </span>
                    @if ($resource->is_premium)
                        <span class="tg-cell-meta">🔒</span>
                    @endif
                    <span class="tg-cell-chevron" aria-hidden="true">›</span>
                </a>
            @endforeach
        </div>
    @endif
</x-telegram.mini-app.layout>
