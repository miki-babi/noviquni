@php
    $deepLinks = app(\App\Services\TelegramDeepLink::class);
    $accentCycle = ['lime', 'mint', 'coral', 'teal'];
    $tabLabels = [
        'resources' => $copy->get('saved.tab_resources'),
        'scholarships' => $copy->get('saved.tab_scholarships'),
        'internships' => $copy->get('saved.tab_internships'),
        'jobs' => $copy->get('saved.tab_jobs'),
        'mentorship' => $copy->get('saved.tab_mentorship'),
    ];
@endphp

<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$copy->get('saved.title')"
    :back-url="route('tg.library')"
>
    <div class="study-page">
        <div class="flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="{{ $copy->get('saved.title') }}">
            @foreach ($tabs as $tab)
                <a
                    href="{{ route('tg.saved', ['tab' => $tab]) }}"
                    role="tab"
                    aria-selected="{{ $activeTab === $tab ? 'true' : 'false' }}"
                    class="shrink-0 rounded-pill border px-3 py-1.5 text-xs font-semibold {{ $activeTab === $tab ? 'border-primary-200 bg-primary-50 text-primary-300' : 'border-border-light text-text-secondary' }}"
                >
                    {{ $tabLabels[$tab] ?? $tab }}
                </a>
            @endforeach
        </div>

        @if ($bookmarks->isEmpty())
            <p class="text-[15px] leading-relaxed tg-hint">
                {{ $copy->get('saved.empty_tab', ['tab' => $tabLabels[$activeTab] ?? $activeTab]) }}
            </p>
            @if ($activeTab === 'resources')
                <a href="{{ route('tg.browse') }}" class="tg-btn">
                    {{ $copy->get('menu.courses') }}
                </a>
            @endif
        @else
            @foreach ($bookmarks as $bookmark)
                @php
                    $item = $bookmark->bookmarkable;
                    $accent = $accentCycle[($loop->index) % count($accentCycle)];
                @endphp
                @if ($item instanceof \App\Models\LearningResource)
                    <x-telegram.mini-app.bot-link
                        :href="$deepLinks->forResource($item->id)"
                        :confirm-title="$copy->get('bot_confirm.title')"
                        :confirm-message="$copy->get('bot_confirm.resource', ['title' => $item->title])"
                        class="study-card study-card--{{ $accent }}"
                    >
                        <div class="study-card-inner">
                            <span class="min-w-0 flex-1">
                                <span class="study-card-title block">{{ $item->title }}</span>
                                <span class="study-card-meta block">
                                    @if ($item->course)
                                        {{ $item->course->name }}
                                    @else
                                        {{ $item->type->label() }}
                                    @endif
                                </span>
                            </span>
                            <span class="tg-cell-chevron" aria-hidden="true">›</span>
                        </div>
                    </x-telegram.mini-app.bot-link>
                @elseif ($item instanceof \App\Models\Opportunity)
                    <a href="{{ route('tg.opportunities.show', $item) }}" class="study-card study-card--{{ $accent }}">
                        <div class="study-card-inner">
                            <span class="min-w-0 flex-1">
                                <span class="study-card-title block">{{ $item->title }}</span>
                                <span class="study-card-meta block">{{ $item->type->label() }}</span>
                            </span>
                            <span class="tg-cell-chevron" aria-hidden="true">›</span>
                        </div>
                    </a>
                @endif
            @endforeach
        @endif
    </div>
</x-telegram.mini-app.layout>
