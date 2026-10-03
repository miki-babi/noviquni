@php
    $deepLinks = app(\App\Services\TelegramDeepLink::class);
@endphp

<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$title"
    :back-url="route('tg.library')"
>
    <div class="study-page" style="padding-inline: 0;">
        @if ($groups->isEmpty())
            <p class="px-4 text-[15px] leading-relaxed tg-hint">{{ $copy->get('library.hub_empty', ['hub' => strtolower($hub->label())]) }}</p>
        @else
            @foreach ($groups as $group)
                <p class="tg-section-title">{{ $group['course_name'] }}</p>
                <div class="tg-section">
                    @foreach ($group['resources'] as $item)
                        @php
                            $resource = $item['resource'];
                            $locked = config('services.telegram.premium_enabled') && $item['locked'];
                        @endphp
                        <x-telegram.mini-app.bot-link
                            :href="$deepLinks->forResource($resource->id)"
                            @class(['tg-cell', 'opacity-70' => $locked])
                        >
                            <span class="tg-cell-body">
                                <span class="tg-cell-title">{{ $resource->title }}</span>
                                <span class="tg-cell-subtitle">{{ $resource->type->label() }}</span>
                            </span>
                            @if ($locked)
                                <span class="tg-cell-meta">🔒</span>
                            @endif
                            <span class="tg-cell-chevron" aria-hidden="true">›</span>
                        </x-telegram.mini-app.bot-link>
                    @endforeach
                </div>
            @endforeach
        @endif
    </div>
</x-telegram.mini-app.layout>
