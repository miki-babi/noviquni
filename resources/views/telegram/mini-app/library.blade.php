<x-telegram.mini-app.layout :copy="$copy" :active-nav="$activeNav" :title="$copy->get('keyboard.resources')">
    <p class="px-4 pb-2 text-[15px] leading-relaxed tg-hint">{{ $copy->get('library.title') }}</p>

    @if ($hubs->every(fn (array $item): bool => $item['count'] === 0))
        <div class="space-y-3 px-4 text-center">
            <x-telegram.lottie name="empty-library" />
            <p class="text-[15px] leading-relaxed tg-hint">{{ $copy->get('library.empty') }}</p>
        </div>
    @else
        <div class="tg-section">
            @foreach ($hubs as $item)
                @if ($item['count'] > 0)
                    <a href="{{ route('tg.library.hub', $item['hub']->value) }}" class="tg-cell">
                        <x-telegram.lottie
                            name="hub-{{ $item['hub']->value }}"
                            class="tg-lottie tg-lottie-hub"
                        />
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
