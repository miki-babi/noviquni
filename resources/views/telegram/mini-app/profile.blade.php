@php
    use App\Enums\TelegramLocale;

    $theme = $user->theme ?? 'dark';
@endphp

<x-telegram.mini-app.layout
    :copy="$copy"
    :user="$user"
    :active-nav="$activeNav"
    :title="$copy->get('keyboard.profile')"
>
    <div class="study-page">
        <div class="study-card study-continue">
            <p class="whitespace-pre-line text-sm leading-relaxed">
                {{ $copy->get('profile.body', [
                    'name' => $user->name,
                    'stream' => $user->stream?->name ?? '-',
                    'university' => $user->university?->name ?? '-',
                    'semester' => $user->semester?->name ?? '-',
                    'courses' => $courses,
                    'premium' => $premium,
                ]) }}
            </p>
        </div>

        <a href="{{ route('tg.premium') }}" class="study-card">
            <div class="study-card-inner">
                <span class="min-w-0 flex-1">
                    <span class="study-card-title block">{{ $copy->get('menu.premium') }}</span>
                </span>
                <span class="tg-cell-chevron" aria-hidden="true">›</span>
            </div>
        </a>

        <form method="POST" action="{{ route('tg.profile.notifications') }}">
            @csrf
            <button type="submit" class="tg-btn tg-btn-secondary tg-chunk">
                {{ $copy->get('profile.notifications') }}
                · {{ $user->notifications_enabled ? $copy->get('notify.on') : $copy->get('notify.off') }}
            </button>
        </form>

        <div class="study-card study-continue">
            <p class="study-card-title">{{ $copy->get('profile.refer') }}</p>
            <p class="mt-2 break-all text-xs tg-hint">{{ $referralLink }}</p>
        </div>

        <div class="study-card study-continue">
            <p class="study-card-title">{{ $copy->get('profile.settings') }}</p>

            <p class="mt-3 text-xs tg-hint">{{ $copy->get('settings.theme') }}</p>
            <div class="mt-2 grid grid-cols-2 gap-3">
                <form method="POST" action="{{ route('tg.profile.theme') }}">
                    @csrf
                    <input type="hidden" name="theme" value="light">
                    <button
                        type="submit"
                        class="tg-btn {{ $theme === 'light' ? '' : 'tg-btn-secondary' }} tg-chunk"
                    >
                        {{ $copy->get('settings.theme_light') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('tg.profile.theme') }}">
                    @csrf
                    <input type="hidden" name="theme" value="dark">
                    <button
                        type="submit"
                        class="tg-btn {{ $theme === 'dark' ? '' : 'tg-btn-secondary' }} tg-chunk"
                    >
                        {{ $copy->get('settings.theme_dark') }}
                    </button>
                </form>
            </div>

            <p class="mt-4 text-xs tg-hint">{{ $copy->get('settings.prompt') }}</p>
            <div class="mt-2 grid grid-cols-2 gap-3">
                <form method="POST" action="{{ route('tg.profile.locale') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ TelegramLocale::English->value }}">
                    <button
                        type="submit"
                        class="tg-btn {{ $user->telegram_locale === 'en' ? '' : 'tg-btn-secondary' }} tg-chunk"
                    >
                        {{ TelegramLocale::English->label() }}
                    </button>
                </form>
                <form method="POST" action="{{ route('tg.profile.locale') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ TelegramLocale::Amharic->value }}">
                    <button
                        type="submit"
                        class="tg-btn {{ $user->telegram_locale === 'am' ? '' : 'tg-btn-secondary' }} tg-chunk"
                    >
                        {{ TelegramLocale::Amharic->label() }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-telegram.mini-app.layout>
