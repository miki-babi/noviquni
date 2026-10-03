@php
    $deepLinks = app(\App\Services\TelegramDeepLink::class);
    $accentCycle = ['lime', 'mint', 'coral', 'teal'];
    $planItemIndex = 0;
@endphp

<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$course->name"
    :back-url="route('tg.browse')"
>
    <div class="study-page">
        @if ($plans->isNotEmpty())
            <p class="tg-section-title" style="padding-inline: 0;">{{ $copy->get('browse.plans') }}</p>
            @foreach ($plans as $plan)
                <div class="study-card study-continue">
                    <p class="study-card-title">{{ $plan->title }}</p>
                    @if ($plan->description)
                        <p class="study-card-meta mt-1">{{ $plan->description }}</p>
                    @endif
                </div>

                @foreach ($plan->items as $item)
                    @if ($item->learningResource)
                        @php
                            $accent = $accentCycle[$planItemIndex % count($accentCycle)];
                            $planItemIndex++;
                            $label = $item->label;
                        @endphp
                        <x-telegram.mini-app.bot-link
                            :href="$deepLinks->forResource($item->learningResource->id)"
                            :confirm-title="$copy->get('bot_confirm.title')"
                            :confirm-message="$copy->get('bot_confirm.resource', ['title' => $label])"
                            class="study-card study-card--{{ $accent }}"
                        >
                            <div class="study-card-inner">
                                <span class="min-w-0 flex-1">
                                    <span class="study-card-title block">{{ $label }}</span>
                                </span>
                                <span class="tg-cell-chevron" aria-hidden="true">›</span>
                            </div>
                        </x-telegram.mini-app.bot-link>
                    @else
                        <div class="study-card study-card-ghost">
                            <div class="study-card-inner">
                                <span class="study-card-title">{{ $item->label }}</span>
                            </div>
                        </div>
                    @endif
                @endforeach
            @endforeach
        @endif

        @if ($hubs->isEmpty())
            <div class="space-y-4">
                <p class="text-[15px] leading-relaxed tg-hint">
                    {!! nl2br(e($copy->get('browse.course_coming_soon', ['course' => $course->name]))) !!}
                </p>
                <form method="POST" action="{{ route('tg.profile.notifications.enable') }}">
                    @csrf
                    <button type="submit" class="tg-btn">
                        {{ $copy->get('browse.notify') }}
                    </button>
                </form>
            </div>
        @else
            <p class="tg-section-title" style="padding-inline: 0;">{{ $copy->get('browse.hubs') }}</p>
            @foreach ($hubs as $item)
                @php
                    $accent = $accentCycle[($loop->index) % count($accentCycle)];
                @endphp
                <a
                    href="{{ route('tg.courses.hub', ['course' => $course, 'hub' => $item['hub']->value]) }}"
                    class="study-card study-card--{{ $accent }}"
                >
                    <div class="study-card-inner">
                        <span class="min-w-0 flex-1">
                            <span class="study-card-title block">{{ $item['label'] }}</span>
                            <span class="study-card-meta block">{{ $item['count'] }}</span>
                        </span>
                        <span class="tg-cell-chevron" aria-hidden="true">›</span>
                    </div>
                </a>
            @endforeach
        @endif
    </div>
</x-telegram.mini-app.layout>
