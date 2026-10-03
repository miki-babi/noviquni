@php
    $deepLinks = app(\App\Services\TelegramDeepLink::class);
@endphp

<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$copy->get('saved.title')"
    :back-url="route('tg.library')"
>
    <div class="study-page" style="padding-inline: 0;">
        @if ($bookmarks->isEmpty())
            <div class="space-y-4 px-4">
                <p class="text-[15px] leading-relaxed tg-hint">{{ $copy->get('saved.empty') }}</p>
                <a href="{{ route('tg.browse') }}" class="tg-btn">
                    {{ $copy->get('menu.courses') }}
                </a>
            </div>
        @else
            <div class="tg-section">
                @foreach ($bookmarks as $bookmark)
                    @php $resource = $bookmark->learningResource; @endphp
                    @if ($resource)
                        <x-telegram.mini-app.bot-link
                            :href="$deepLinks->forResource($resource->id)"
                            class="tg-cell"
                        >
                            <span class="tg-cell-body">
                                <span class="tg-cell-title">{{ $resource->title }}</span>
                                @if ($resource->course)
                                    <span class="tg-cell-subtitle">{{ $resource->course->name }}</span>
                                @endif
                            </span>
                            <span class="tg-cell-chevron" aria-hidden="true">›</span>
                        </x-telegram.mini-app.bot-link>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</x-telegram.mini-app.layout>
