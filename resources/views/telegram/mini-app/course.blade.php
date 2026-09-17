<x-telegram.mini-app.layout :copy="$copy" :active-nav="$activeNav" :title="$course->name">
    <p class="px-4 pb-2 text-[15px] leading-relaxed tg-hint">
        {{ $copy->get('browse.path_intro') }}
    </p>

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

    @if ($steps->isEmpty())
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
        <p class="tg-section-title">{{ $copy->get('browse.path') }}</p>
        <div class="tg-section">
            @foreach ($steps as $item)
                @php
                    $resource = $item['resource'];
                    $locked = $item['locked'];
                    $completed = $item['completed'];
                @endphp
                <a href="{{ $resource->miniAppUrl() }}" class="tg-cell {{ $locked ? 'opacity-70' : '' }}">
                    <span class="tg-cell-body">
                        <span class="tg-cell-title">{{ $resource->title }}</span>
                        <span class="tg-cell-subtitle">{{ $resource->type->label() }}</span>
                    </span>
                    <span class="tg-cell-meta">
                        @if ($completed)✓@endif
                        @if ($locked)🔒@endif
                    </span>
                    <span class="tg-cell-chevron" aria-hidden="true">›</span>
                </a>
            @endforeach
        </div>
    @endif

    @if ($isPremium && $archiveHubs->isNotEmpty())
        <p class="tg-section-title">{{ $copy->get('browse.archive') }}</p>
        <div class="tg-section">
            @foreach ($archiveHubs as $item)
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

    <div class="px-4 pt-2">
        <a href="{{ route('tg.browse') }}" class="tg-btn tg-btn-secondary">
            {{ $copy->get('hub.back') }}
        </a>
    </div>
</x-telegram.mini-app.layout>
