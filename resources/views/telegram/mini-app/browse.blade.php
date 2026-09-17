<x-telegram.mini-app.layout
    :copy="$copy"
    :user="$user"
    :active-nav="$activeNav"
    :title="$copy->get('browse.page_title')"
>
    <div class="study-page">
        @if ($state === 'empty')
            <p class="whitespace-pre-line text-[15px] leading-relaxed tg-hint">{{ $copy->get('browse.empty') }}</p>
            <a
                href="https://t.me/{{ config('services.telegram.bot_username') }}?start=setup"
                class="tg-btn"
                onclick="window.Telegram?.WebApp?.openTelegramLink(this.href); return false;"
            >
                {{ $copy->get('browse.empty_button') }}
            </a>
        @elseif ($state === 'coming_soon')
            <p class="whitespace-pre-line text-[15px] leading-relaxed tg-hint">{{ $copy->get('browse.coming_soon') }}</p>

            @foreach ($courseRows as $row)
                @php
                    $course = $row['course'];
                    $initial = mb_strtoupper(mb_substr($course->name, 0, 1));
                @endphp
                <a href="{{ route('tg.courses.show', $course) }}" class="study-card">
                    <div class="study-card-inner">
                        <span class="study-initial" aria-hidden="true">{{ $initial }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="study-card-title block">{{ $course->name }}</span>
                            <span class="study-card-meta block">{{ $copy->get('browse.coming_soon_meta') }}</span>
                        </span>
                        <span class="tg-cell-chevron" aria-hidden="true">›</span>
                    </div>
                </a>
            @endforeach

            <form method="POST" action="{{ route('tg.profile.notifications.enable') }}">
                @csrf
                <button type="submit" class="tg-btn">
                    {{ $copy->get('browse.notify') }}
                </button>
            </form>

            <a
                href="https://t.me/{{ config('services.telegram.bot_username') }}?start=setup"
                class="study-card study-card-ghost"
                onclick="window.Telegram?.WebApp?.openTelegramLink(this.href); return false;"
            >
                <div class="study-card-inner">
                    <span class="study-card-title">{{ $copy->get('browse.add_course') }}</span>
                </div>
            </a>
        @else
            @if ($continue)
                @php
                    $progressPct = $continue['total'] > 0
                        ? (int) round(($continue['completed'] / $continue['total']) * 100)
                        : 0;
                @endphp
                <article class="study-card study-continue">
                    <p class="study-continue-label">{{ $copy->get('browse.continue') }}</p>
                    <h2 class="study-continue-title">{{ $continue['course']->name }}</h2>
                    <p class="study-continue-sub">
                        {{ $continue['next']->title }}
                        @if ($continue['next']->type)
                            · {{ $continue['next']->type->label() }}
                        @endif
                    </p>
                    @if ($continue['total'] > 0)
                        <div class="study-progress" aria-hidden="true">
                            <span style="width: {{ $progressPct }}%;"></span>
                        </div>
                    @endif
                    <a href="{{ $continue['next']->miniAppUrl() }}" class="study-resume">
                        {{ $copy->get('browse.resume') }}
                    </a>
                </article>
            @endif

            <p class="tg-section-title" style="padding-inline: 0;">{{ $copy->get('browse.my_courses') }}</p>

            @foreach ($courseRows as $row)
                @php
                    $course = $row['course'];
                    $initial = mb_strtoupper(mb_substr($course->name, 0, 1));
                    $meta = $row['path_complete']
                        ? $copy->get('browse.path_complete')
                        : $copy->get('browse.resources_count', ['count' => $row['resource_count']]);
                @endphp
                <a href="{{ route('tg.courses.show', $course) }}" class="study-card">
                    <div class="study-card-inner">
                        <span class="study-initial" aria-hidden="true">{{ $initial }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="study-card-title block">{{ $course->name }}</span>
                            <span class="study-card-meta block">{{ $meta }}</span>
                        </span>
                        <span class="tg-cell-chevron" aria-hidden="true">›</span>
                    </div>
                </a>
            @endforeach

            <a
                href="https://t.me/{{ config('services.telegram.bot_username') }}?start=setup"
                class="study-card study-card-ghost"
                onclick="window.Telegram?.WebApp?.openTelegramLink(this.href); return false;"
            >
                <div class="study-card-inner">
                    <span class="study-card-title">{{ $copy->get('browse.add_course') }}</span>
                </div>
            </a>
        @endif
    </div>
</x-telegram.mini-app.layout>
