<x-telegram.mini-app.layout :copy="$copy" :active-nav="$activeNav" :title="$copy->get('keyboard.resources')">
    <p class="px-4 pb-2 text-[15px] leading-relaxed tg-hint">{{ $copy->get('library.title') }}</p>

    @if ($hubs->every(fn (array $item): bool => $item['count'] === 0))
        <p class="px-4 text-[15px] leading-relaxed tg-hint">{{ $copy->get('library.empty') }}</p>
    @else
        <div class="tg-section">
            @foreach ($hubs as $item)
                @if ($item['count'] > 0)
                    <a href="{{ route('tg.library.hub', $item['hub']->value) }}" class="tg-cell">
                        <span class="tg-cell-body">
                            <span class="tg-cell-title">{{ $item['label'] }}</span>
                        </span>
                        <span class="tg-cell-meta">{{ $item['count'] }}</span>
                        <span class="tg-cell-chevron" aria-hidden="true">›</span>
                    </a>
                @endif
            @endforeach
        </div>
    @endif
</x-telegram.mini-app.layout>
