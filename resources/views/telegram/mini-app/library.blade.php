@php
    $accentCycle = ['lime', 'mint', 'coral', 'teal'];
@endphp

<x-telegram.mini-app.layout
    :copy="$copy"
    :user="$user"
    :active-nav="$activeNav"
    :title="$copy->get('menu.resources')"
>
    <div class="study-page">
        <p class="text-[15px] leading-relaxed tg-hint">{{ $copy->get('library.title') }}</p>

        <a href="{{ route('tg.saved') }}" class="study-card study-card--coral">
            <div class="study-card-inner">
                <span class="min-w-0 flex-1">
                    <span class="study-card-title block">{{ $copy->get('library.saved_link') }}</span>
                </span>
                <span class="tg-cell-chevron" aria-hidden="true">›</span>
            </div>
        </a>

        @if ($hubs->every(fn (array $item): bool => $item['count'] === 0))
            <p class="text-[15px] leading-relaxed tg-hint">{{ $copy->get('library.empty') }}</p>
        @else
            @foreach ($hubs as $item)
                @if ($item['count'] > 0)
                    @php
                        $accent = $accentCycle[($loop->index) % count($accentCycle)];
                    @endphp
                    <a href="{{ route('tg.library.hub', $item['hub']->value) }}" class="study-card study-card--{{ $accent }}">
                        <div class="study-card-inner">
                            <span class="min-w-0 flex-1">
                                <span class="study-card-title block">{{ $item['label'] }}</span>
                            </span>
                            <span class="study-card-meta">{{ $item['count'] }}</span>
                            <span class="tg-cell-chevron" aria-hidden="true">›</span>
                        </div>
                    </a>
                @endif
            @endforeach
        @endif
    </div>
</x-telegram.mini-app.layout>
