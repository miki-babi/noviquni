<x-telegram.mini-app.layout :copy="$copy" :active-nav="$activeNav" :title="$title">
    <div class="space-y-5">
        <p class="text-sm text-text-secondary">{{ $copy->get('library.title') }}</p>

        @if ($resources->isEmpty())
            <p class="text-base text-text-secondary">{{ $copy->get('library.hub_empty', ['hub' => strtolower($hub->label())]) }}</p>
        @else
            <div class="grid gap-3">
                @foreach ($resources as $resource)
                    <a
                        href="{{ $resource->miniAppUrl() }}"
                        class="rounded-2xl border border-border-light px-4 py-3 text-left text-sm font-semibold text-text-primary"
                    >
                        @if ($resource->is_premium || ! $resource->is_bait)
                            🔒
                        @endif
                        {{ $resource->title }}
                        @if ($resource->course)
                            <span class="mt-1 block text-xs font-normal text-text-muted">{{ $resource->course->name }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif

        <a href="{{ route('tg.library') }}" class="inline-flex w-full items-center justify-center rounded-2xl border border-border-light px-4 py-3 text-sm font-semibold">
            {{ $copy->get('library.back') }}
        </a>
    </div>
</x-telegram.mini-app.layout>
