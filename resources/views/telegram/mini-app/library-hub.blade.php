@php
    $deepLinks = app(\App\Services\TelegramDeepLink::class);
    $accentCycle = ['lime', 'mint', 'coral', 'teal'];
    $cardIndex = 0;
@endphp

<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$title"
    :back-url="route('tg.library')"
>
    <div class="study-page">
        @if ($groups->isEmpty())
            <p class="text-[15px] leading-relaxed tg-hint">{{ $copy->get('library.hub_empty', ['hub' => strtolower($hub->label())]) }}</p>
        @else
            @foreach ($groups as $group)
                <p class="tg-section-title" style="padding-inline: 0;">{{ $group['course_name'] }}</p>
                @foreach ($group['resources'] as $item)
                    @php
                        $resource = $item['resource'];
                        $locked = $item['locked'];
                        $accent = $accentCycle[$cardIndex % count($accentCycle)];
                        $cardIndex++;
                    @endphp
                    <x-telegram.mini-app.bot-link
                        :href="$deepLinks->forResource($resource->id)"
                        :confirm-title="$copy->get('bot_confirm.title')"
                        :confirm-message="$copy->get('bot_confirm.resource', ['title' => $resource->title])"
                        @class(['study-card', 'study-card--'.$accent, 'opacity-70' => $locked])
                    >
                        <div class="study-card-inner">
                            <span class="min-w-0 flex-1">
                                <span class="study-card-title block">{{ $resource->title }}</span>
                                <span class="study-card-meta block">
                                    {{ $resource->type->label() }}
                                    @if ($locked)
                                        · 🔒
                                    @endif
                                </span>
                            </span>
                            <span class="tg-cell-chevron" aria-hidden="true">›</span>
                        </div>
                    </x-telegram.mini-app.bot-link>
                @endforeach
            @endforeach
        @endif
    </div>
</x-telegram.mini-app.layout>
