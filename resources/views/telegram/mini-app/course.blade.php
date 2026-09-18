<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$course->name"
    :back-url="route('tg.browse')"
>
    @if ($plans->isNotEmpty())
        <p class="tg-section-title">{{ $copy->get('browse.plans') }}</p>
        @foreach ($plans as $plan)
            <div class="tg-section">
                <div class="tg-cell" style="cursor: default;">
                    <span class="tg-cell-body">
                        <span class="tg-cell-title">{{ $plan->title }}</span>
                        @if ($plan->description)
                            <span class="tg-cell-subtitle">{{ $plan->description }}</span>
                        @endif
                    </span>
                </div>
                @foreach ($plan->items as $item)
                    @if ($item->learningResource)
                        <a href="{{ $item->learningResource->miniAppUrl() }}" class="tg-cell">
                            <span class="tg-cell-body">
                                <span class="tg-cell-title">{{ $item->label }}</span>
                            </span>
                            <span class="tg-cell-chevron" aria-hidden="true">›</span>
                        </a>
                    @else
                        <div class="tg-cell" style="cursor: default;">
                            <span class="tg-cell-body">
                                <span class="tg-cell-title tg-hint">{{ $item->label }}</span>
                            </span>
                        </div>
                    @endif
                @endforeach
            </div>
        @endforeach
    @endif

    @if ($hubs->isEmpty())
        <div class="space-y-4 px-4">
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
        <p class="tg-section-title">{{ $copy->get('browse.hubs') }}</p>
        <div class="tg-section">
            @foreach ($hubs as $item)
                <a
                    href="{{ route('tg.courses.hub', ['course' => $course, 'hub' => $item['hub']->value]) }}"
                    class="tg-cell"
                >
                    <span class="tg-cell-body">
                        <span class="tg-cell-title">{{ $item['label'] }}</span>
                    </span>
                    <span class="tg-cell-meta">{{ $item['count'] }}</span>
                    <span class="tg-cell-chevron" aria-hidden="true">›</span>
                </a>
            @endforeach
        </div>
    @endif
</x-telegram.mini-app.layout>
