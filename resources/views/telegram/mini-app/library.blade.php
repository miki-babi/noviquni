<x-telegram.mini-app.layout
    :copy="$copy"
    :user="$user"
    :active-nav="$activeNav"
    :title="$copy->get('keyboard.resources')"
>
    <div class="study-page">
        <p class="text-[15px] leading-relaxed tg-hint">{{ $copy->get('library.title') }}</p>

        <a href="{{ route('tg.saved') }}" class="study-card">
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
                    <a href="{{ route('tg.library.hub', $item['hub']->value) }}" class="study-card">
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
