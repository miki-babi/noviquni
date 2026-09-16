@php
    use App\Enums\TelegramLocale;
@endphp

<x-telegram.mini-app.layout :copy="$copy" :active-nav="$activeNav" :title="$copy->get('keyboard.profile')">
    <div class="space-y-6">
        <div class="whitespace-pre-line rounded-2xl border border-border-light px-4 py-4 text-sm leading-relaxed">
            {{ $copy->get('profile.body', [
                'name' => $user->name,
                'stream' => $user->stream?->name ?? '-',
                'university' => $user->university?->name ?? '-',
                'semester' => $user->semester?->name ?? '-',
                'courses' => $courses,
                'premium' => $premium,
            ]) }}
        </div>

        <div class="grid gap-3">
            <form method="POST" action="{{ route('tg.profile.notifications') }}">
                @csrf
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl border border-border-light px-4 py-3 text-sm font-semibold">
                    {{ $copy->get('profile.notifications') }}
                    · {{ $user->notifications_enabled ? $copy->get('notify.on') : $copy->get('notify.off') }}
                </button>
            </form>

            <div class="rounded-2xl border border-border-light px-4 py-4 space-y-2">
                <p class="text-sm font-semibold">{{ $copy->get('profile.refer') }}</p>
                <p class="break-all text-xs text-text-secondary">{{ $referralLink }}</p>
            </div>

            <div class="rounded-2xl border border-border-light px-4 py-4 space-y-3">
                <p class="text-sm font-semibold">{{ $copy->get('profile.settings') }}</p>
                <p class="text-xs text-text-secondary">{{ $copy->get('settings.prompt') }}</p>
                <div class="grid grid-cols-2 gap-3">
                    <form method="POST" action="{{ route('tg.profile.locale') }}">
                        @csrf
                        <input type="hidden" name="locale" value="{{ TelegramLocale::English->value }}">
                        <button type="submit" class="w-full rounded-2xl px-3 py-2 text-sm font-semibold {{ $user->telegram_locale === 'en' ? 'bg-primary-600 text-white' : 'border border-border-light' }}">
                            {{ TelegramLocale::English->label() }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('tg.profile.locale') }}">
                        @csrf
                        <input type="hidden" name="locale" value="{{ TelegramLocale::Amharic->value }}">
                        <button type="submit" class="w-full rounded-2xl px-3 py-2 text-sm font-semibold {{ $user->telegram_locale === 'am' ? 'bg-primary-600 text-white' : 'border border-border-light' }}">
                            {{ TelegramLocale::Amharic->label() }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-telegram.mini-app.layout>
