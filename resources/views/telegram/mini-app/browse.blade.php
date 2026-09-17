<x-telegram.mini-app.layout :copy="$copy" :active-nav="$activeNav" :title="$copy->get('keyboard.courses')">
    @if ($state === 'empty')
        <div class="space-y-4 px-4 text-center">
            <x-telegram.lottie name="empty-courses" />
            <p class="whitespace-pre-line text-[15px] leading-relaxed tg-hint">{{ $copy->get('browse.empty') }}</p>
            <a
                href="https://t.me/{{ config('services.telegram.bot_username') }}?start=setup"
                class="tg-btn"
                onclick="window.Telegram?.WebApp?.openTelegramLink(this.href); return false;"
            >
                {{ $copy->get('browse.empty_button') }}
            </a>
        </div>
    @elseif ($state === 'coming_soon')
        <div class="space-y-4">
            <div class="px-4 text-center">
                <x-telegram.lottie name="empty-courses" />
            </div>
            <p class="whitespace-pre-line px-4 text-[15px] leading-relaxed tg-hint">{{ $copy->get('browse.coming_soon') }}</p>
            <div class="tg-section">
                @foreach ($courseRows as $row)
                    <a href="{{ route('tg.courses.show', $row['course']) }}" class="tg-cell">
                        <span class="tg-cell-body">
                            <span class="tg-cell-title">{{ $row['course']->name }}</span>
                        </span>
                        <span class="tg-cell-chevron" aria-hidden="true">›</span>
                    </a>
                @endforeach
            </div>
            <form method="POST" action="{{ route('tg.profile.notifications.enable') }}" class="px-4">
                @csrf
                <button type="submit" class="tg-btn">
                    {{ $copy->get('browse.notify') }}
                </button>
            </form>
        </div>
    @else
        <div class="space-y-1">
            <p class="px-4 pb-1 text-[15px] leading-relaxed tg-hint">{{ $copy->get('browse.has_content') }}</p>
            <div class="tg-section">
                @foreach ($courseRows as $row)
                    <a href="{{ route('tg.courses.show', $row['course']) }}" class="tg-cell">
                        <span class="tg-cell-body">
                            <span class="tg-cell-title">{{ $row['course']->name }}</span>
                            <span class="tg-cell-subtitle">
                                @if ($row['path_complete'])
                                    {{ $copy->get('browse.path_complete') }}
                                @elseif ($row['next'])
                                    {{ $copy->get('browse.next_step', ['title' => $row['next']->title]) }}
                                @else
                                    {{ $copy->get('browse.no_path') }}
                                @endif
                            </span>
                        </span>
                        @if ($row['total'] > 0)
                            <span class="tg-cell-meta">{{ $row['completed'] }}/{{ $row['total'] }}</span>
                        @endif
                        <span class="tg-cell-chevron" aria-hidden="true">›</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</x-telegram.mini-app.layout>
