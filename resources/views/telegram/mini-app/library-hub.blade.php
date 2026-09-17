<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$title"
    :back-url="route('tg.library')"
>
    @if ($groups->isEmpty())
        <p class="px-4 text-[15px] leading-relaxed tg-hint">{{ $copy->get('library.hub_empty', ['hub' => strtolower($hub->label())]) }}</p>
    @else
        @foreach ($groups as $group)
            <p class="tg-section-title">{{ $group['course_name'] }}</p>
            <div class="tg-section">
                @foreach ($group['resources'] as $item)
                    @php
                        $resource = $item['resource'];
                        $locked = $item['locked'];
                    @endphp
                    <a href="{{ $resource->miniAppUrl() }}" class="tg-cell {{ $locked ? 'opacity-70' : '' }}">
                        <span class="tg-cell-body">
                            <span class="tg-cell-title">{{ $resource->title }}</span>
                            <span class="tg-cell-subtitle">{{ $resource->type->label() }}</span>
                        </span>
                        @if ($locked)
                            <span class="tg-cell-meta">🔒</span>
                        @endif
                        <span class="tg-cell-chevron" aria-hidden="true">›</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    @endif
</x-telegram.mini-app.layout>
