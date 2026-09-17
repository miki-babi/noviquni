<x-telegram.mini-app.layout :copy="$copy" :active-nav="$activeNav" :title="$copy->get('keyboard.resources')">
    <div class="space-y-5">
        <p class="text-base text-text-secondary">{{ $copy->get('library.title') }}</p>

        @if ($hubs->every(fn (array $item): bool => $item['count'] === 0))
            <p class="text-base text-text-secondary">{{ $copy->get('library.empty') }}</p>
        @else
            <div class="grid gap-3">
                @foreach ($hubs as $item)
                    @if ($item['count'] > 0)
                        <a
                            href="{{ route('tg.library.hub', $item['hub']->value) }}"
                            class="flex items-center justify-between rounded-2xl border border-border-light px-4 py-3 text-left text-sm font-semibold text-text-primary"
                        >
                            <span>{{ $item['label'] }}</span>
                            <span class="text-xs text-text-muted">{{ $item['count'] }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</x-telegram.mini-app.layout>
