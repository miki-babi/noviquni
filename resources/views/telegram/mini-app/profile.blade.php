@php
    use App\Enums\TelegramLocale;

    $theme = $user->theme ?? 'dark';
    $initial = mb_strtoupper(mb_substr($user->name ?: 'S', 0, 1));
    $photoUrl = $user->telegram_photo_url;
    $username = $user->telegram_username;
@endphp

<x-telegram.mini-app.layout
    :copy="$copy"
    :user="$user"
    :active-nav="$activeNav"
    :title="$copy->get('keyboard.profile')"
>
    <div
        class="study-page"
        x-data="{
            photoUrl: @js($photoUrl),
            displayName: @js($user->name),
            username: @js($username),
            init() {
                const tgUser = window.Telegram?.WebApp?.initDataUnsafe?.user;
                if (! tgUser) {
                    return;
                }
                if (tgUser.photo_url) {
                    this.photoUrl = tgUser.photo_url;
                }
                const name = [tgUser.first_name, tgUser.last_name].filter(Boolean).join(' ').trim();
                if (name) {
                    this.displayName = name;
                }
                if (tgUser.username) {
                    this.username = tgUser.username;
                }
            },
        }"
    >
        <div class="study-profile-hero">
            <span class="tg-avatar tg-avatar-lg" aria-hidden="true">
                <img
                    x-show="photoUrl"
                    x-cloak
                    :src="photoUrl"
                    @if ($photoUrl) src="{{ $photoUrl }}" @endif
                    alt=""
                    class="tg-avatar-img"
                >
                <span x-show="! photoUrl" x-text="displayName.charAt(0).toUpperCase()">{{ $initial }}</span>
            </span>
            <div class="min-w-0">
                <h2 class="study-profile-name" x-text="displayName">{{ $user->name }}</h2>
                <p class="study-profile-username" x-show="username" x-text="username ? '@' + username : ''">
                    @if ($username)
                        {{ '@'.$username }}
                    @endif
                </p>
            </div>
        </div>

        <div class="study-card study-continue">
            <p class="whitespace-pre-line text-sm leading-relaxed">
                {{ $copy->get('profile.body', [
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
