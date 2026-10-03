@php
    $deepLinks = app(\App\Services\TelegramDeepLink::class);
    $accentCycle = ['lime', 'mint', 'coral', 'teal'];
@endphp

<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$copy->get('saved.title')"
    :back-url="route('tg.library')"
>
    <div class="study-page">
        @if ($bookmarks->isEmpty())
            <p class="text-[15px] leading-relaxed tg-hint">{{ $copy->get('saved.empty') }}</p>
            <a href="{{ route('tg.browse') }}" class="tg-btn">
                {{ $copy->get('menu.courses') }}
            </a>
        @else
            @foreach ($bookmarks as $bookmark)
                @php
                    $resource = $bookmark->learningResource;
                    $accent = $accentCycle[($loop->index) % count($accentCycle)];
                @endphp
                @if ($resource)
                    <x-telegram.mini-app.bot-link
                        :href="$deepLinks->forResource($resource->id)"
                        :confirm-title="$copy->get('bot_confirm.title')"
                        :confirm-message="$copy->get('bot_confirm.resource', ['title' => $resource->title])"
                        class="study-card study-card--{{ $accent }}"
                    >
                        <div class="study-card-inner">
                            <span class="min-w-0 flex-1">
                                <span class="study-card-title block">{{ $resource->title }}</span>
                                <span class="study-card-meta block">
                                    @if ($resource->course)
                                        {{ $resource->course->name }}
                                    @else
                                        {{ $resource->type->label() }}
                                    @endif
                                </span>
                            </span>
                            <span class="tg-cell-chevron" aria-hidden="true">›</span>
                        </div>
                    </x-telegram.mini-app.bot-link>
                @endif
            @endforeach
        @endif
    </div>
</x-telegram.mini-app.layout>
