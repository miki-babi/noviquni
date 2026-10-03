@php
    use App\Enums\TelegramLocale;

    $theme = $user->theme ?? 'dark';
    $initial = mb_strtoupper(mb_substr($user->name ?: 'S', 0, 1));
    $photoUrl = $user->telegram_photo_url;
    $username = $user->telegram_username;
    $accentCycle = ['lime', 'mint', 'coral', 'teal'];
@endphp

<x-telegram.mini-app.layout
    :copy="$copy"
    :user="$user"
    :active-nav="$activeNav"
    :title="$copy->get('menu.profile')"
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
            openBotLink(event) {
                const href = event.currentTarget.getAttribute('href');
                if (! href) {
                    return;
                }
                const webApp = window.Telegram?.WebApp;
                if (webApp?.openTelegramLink) {
                    event.preventDefault();
                    webApp.openTelegramLink(href);
                    webApp.close?.();
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
                <h2 class="study-profile-name" x-text="displayName.toUpperCase()">{{ mb_strtoupper($user->name ?: '') }}</h2>
                <p class="study-profile-username" x-show="username" x-text="username ? '@' + username : ''">
                    @if ($username)
                        {{ '@'.$username }}
                    @endif
                </p>
            </div>
        </div>

        <div class="study-card study-continue">
            <div class="study-profile-row">
                <span class="study-profile-label">{{ $copy->get('profile.stream') }}</span>
                <span class="study-profile-value">{{ $user->stream?->name ?? $copy->get('profile.none') }}</span>
            </div>
            <div class="study-profile-row">
                <span class="study-profile-label">{{ $copy->get('profile.university') }}</span>
                <span class="study-profile-value">{{ $user->university?->name ?? $copy->get('profile.none') }}</span>
            </div>
            <div class="study-profile-row">
                <span class="study-profile-label">{{ $copy->get('profile.semester') }}</span>
                <span class="study-profile-value">{{ $user->semester?->name ?? $copy->get('profile.none') }}</span>
            </div>
            <div class="study-profile-row study-profile-row--stack">
                <span class="study-profile-label">{{ $copy->get('profile.courses') }}</span>
                @if ($courses->isEmpty())
                    <span class="study-profile-value">{{ $copy->get('profile.none') }}</span>
                @else
                    <div class="study-chip-wrap">
                        @foreach ($courses as $course)
                            @php
                                $accent = $accentCycle[($loop->index) % count($accentCycle)];
                            @endphp
                            <a
                                href="{{ $deepLinks->forCourse($course->slug) }}"
                                class="study-chip study-chip--{{ $accent }}"
                                @click="openBotLink($event)"
                            >
                                {{ $course->name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="study-card study-continue">
            <div class="flex items-center justify-between gap-3">
                <p class="study-card-title">{{ $copy->get('profile.files') }}</p>
                <a href="{{ route('tg.saved') }}" class="text-xs font-semibold tg-link">{{ $copy->get('menu.saved') }}</a>
            </div>

            @if ($bookmarks->isEmpty())
                <p class="mt-3 text-sm leading-relaxed tg-hint">{{ $copy->get('profile.files_empty') }}</p>
                <a href="{{ route('tg.library') }}" class="tg-btn tg-btn-secondary mt-3">
                    {{ $copy->get('menu.resources') }}
                </a>
            @else
                <div class="study-file-list mt-3">
                    @foreach ($bookmarks as $bookmark)
                        @php $resource = $bookmark->learningResource; @endphp
                        @if ($resource)
                            <a
                                href="{{ $deepLinks->forResource($resource->id) }}"
                                class="study-file-row"
                                @click="openBotLink($event)"
                            >
                                <span class="min-w-0 flex-1">
                                    <span class="study-card-title block">{{ $resource->title }}</span>
                                    <span class="study-card-meta block">
                                        {{ $resource->type->label() }}
                                        @if ($resource->course)
                                            · {{ $resource->course->name }}
                                        @endif
                                    </span>
                                </span>
                                <span class="tg-cell-chevron" aria-hidden="true">›</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        <a href="{{ route('tg.profile.edit') }}" class="study-card">
            <div class="study-card-inner">
                <span class="min-w-0 flex-1">
                    <span class="study-card-title block">{{ $copy->get('profile.edit_study') }}</span>
                </span>
                <span class="tg-cell-chevron" aria-hidden="true">›</span>
            </div>
        </a>

        @if ($premiumEnabled ?? false)
            <a href="{{ route('tg.premium') }}" class="study-card">
                <div class="study-card-inner">
                    <span class="min-w-0 flex-1">
                        <span class="study-card-title block">{{ $copy->get('menu.premium') }}</span>
                    </span>
                    <span class="tg-cell-chevron" aria-hidden="true">›</span>
                </div>
            </a>
        @endif

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
