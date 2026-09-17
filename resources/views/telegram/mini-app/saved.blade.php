<x-telegram.mini-app.layout :copy="$copy" :active-nav="$activeNav" :title="$copy->get('saved.title')">
    <div class="space-y-5">
        @if ($bookmarks->isEmpty())
            <p class="text-base leading-relaxed text-text-secondary">{{ $copy->get('saved.empty') }}</p>
            <a
                href="{{ route('tg.browse') }}"
                class="inline-flex w-full items-center justify-center rounded-2xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white"
            >
                {{ $copy->get('keyboard.courses') }}
            </a>
        @else
            <div class="grid gap-3">
                @foreach ($bookmarks as $bookmark)
                    @php $resource = $bookmark->learningResource; @endphp
                    <a
                        href="{{ $resource->miniAppUrl() }}"
                        class="rounded-2xl border border-border-light px-4 py-3 text-left text-sm font-semibold text-text-primary"
                    >
                        {{ $resource->title }}
                        @if ($resource->course)
                            <span class="mt-1 block text-xs font-normal text-text-muted">{{ $resource->course->name }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-telegram.mini-app.layout>
