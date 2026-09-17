<x-telegram.mini-app.layout :copy="$copy" :active-nav="$activeNav" :title="$copy->get('saved.title')">
    @if ($bookmarks->isEmpty())
        <div class="space-y-4 px-4 text-center">
            <x-telegram.lottie name="empty-saved" />
            <p class="text-[15px] leading-relaxed tg-hint">{{ $copy->get('saved.empty') }}</p>
            <a href="{{ route('tg.browse') }}" class="tg-btn">
                {{ $copy->get('keyboard.courses') }}
            </a>
        </div>
    @else
        <div class="tg-section">
            @foreach ($bookmarks as $bookmark)
                @php $resource = $bookmark->learningResource; @endphp
                @if ($resource)
                    <a href="{{ $resource->miniAppUrl() }}" class="tg-cell">
                        <span class="tg-cell-body">
                            <span class="tg-cell-title">{{ $resource->title }}</span>
                            @if ($resource->course)
                                <span class="tg-cell-subtitle">{{ $resource->course->name }}</span>
                            @endif
                        </span>
                        <span class="tg-cell-chevron" aria-hidden="true">›</span>
                    </a>
                @endif
            @endforeach
        </div>
    @endif
</x-telegram.mini-app.layout>
