@php
    $deepLinks = app(\App\Services\TelegramDeepLink::class);
@endphp

<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$title"
    :back-url="route('tg.courses.show', $course)"
>
    <div class="study-page" style="padding-inline: 0;">
        <p class="tg-section-title">{{ $course->name }}</p>

        @if ($resources->isEmpty())
            <p class="px-4 text-[15px] leading-relaxed tg-hint">{{ $copy->get('hub.no_resources') }}</p>
        @else
            <div class="tg-section">
                @foreach ($resources as $resource)
                    <x-telegram.mini-app.bot-link
                        :href="$deepLinks->forResource($resource->id)"
                        class="tg-cell"
                    >
                        <span class="tg-cell-body">
                            <span class="tg-cell-title">{{ $resource->title }}</span>
                        </span>
                        @if (config('services.telegram.premium_enabled') && $resource->is_premium)
                            <span class="tg-cell-meta">🔒</span>
                        @endif
                        <span class="tg-cell-chevron" aria-hidden="true">›</span>
                    </x-telegram.mini-app.bot-link>
                @endforeach
            </div>
        @endif
    </div>
</x-telegram.mini-app.layout>
