<x-telegram.mini-app.layout :copy="$copy" :active-nav="$activeNav" :title="$copy->get('keyboard.browse')">
    @if ($state === 'empty')
        <div class="space-y-5">
            <p class="whitespace-pre-line text-base leading-relaxed text-text-secondary">{{ $copy->get('browse.empty') }}</p>
            <a
                href="https://t.me/{{ config('services.telegram.bot_username') }}?start=setup"
                class="inline-flex w-full items-center justify-center rounded-2xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white"
                onclick="window.Telegram?.WebApp?.openTelegramLink(this.href); return false;"
            >
                {{ $copy->get('browse.empty_button') }}
            </a>
        </div>
    @elseif ($state === 'coming_soon')
        <div class="space-y-5">
            <p class="whitespace-pre-line text-base leading-relaxed text-text-secondary">{{ $copy->get('browse.coming_soon') }}</p>
            <div class="grid gap-3">
                @foreach ($courses as $course)
                    <a
                        href="{{ route('tg.courses.show', $course) }}"
                        class="rounded-2xl border border-border-light px-4 py-3 text-left text-sm font-semibold text-text-primary"
                    >
                        {{ $course->name }}
                    </a>
                @endforeach
            </div>
            <form method="POST" action="{{ route('tg.profile.notifications.enable') }}">
                @csrf
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white">
                    {{ $copy->get('browse.notify') }}
                </button>
            </form>
        </div>
    @else
        <div class="space-y-5">
            <p class="text-base leading-relaxed text-text-secondary">{{ $copy->get('browse.has_content') }}</p>
            <div class="grid gap-3">
                @foreach ($courses as $course)
                    <a
                        href="{{ route('tg.courses.show', $course) }}"
                        class="rounded-2xl border border-border-light px-4 py-3 text-left text-sm font-semibold text-text-primary"
                    >
                        {{ $course->name }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</x-telegram.mini-app.layout>
