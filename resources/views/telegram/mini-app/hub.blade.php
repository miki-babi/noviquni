@php
    $deepLinks = app(\App\Services\TelegramDeepLink::class);
    $accentCycle = ['lime', 'mint', 'coral', 'teal'];
@endphp

<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$title"
    :back-url="route('tg.courses.show', $course)"
>
    <div class="study-page">
        <p class="tg-section-title" style="padding-inline: 0;">{{ $course->name }}</p>

        @if ($resources->isEmpty())
            <p class="text-[15px] leading-relaxed tg-hint">{{ $copy->get('hub.no_resources') }}</p>
        @else
            @foreach ($resources as $resource)
                @php
                    $accent = $accentCycle[($loop->index) % count($accentCycle)];
                @endphp
                <x-telegram.mini-app.bot-link
                    :href="$deepLinks->forResource($resource->id)"
                    :confirm-title="$copy->get('bot_confirm.title')"
                    :confirm-message="$copy->get('bot_confirm.resource', ['title' => $resource->title])"
                    class="study-card study-card--{{ $accent }}"
                >
                    <div class="study-card-inner">
                        <span class="min-w-0 flex-1">
                            <span class="study-card-title block">{{ $resource->title }}</span>
                            @if ($resource->is_premium)
                                <span class="study-card-meta block">🔒 Premium</span>
                            @endif
                        </span>
                        <span class="tg-cell-chevron" aria-hidden="true">›</span>
                    </div>
                </x-telegram.mini-app.bot-link>
            @endforeach
        @endif
    </div>
</x-telegram.mini-app.layout>
