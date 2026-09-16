<x-telegram.mini-app.layout :copy="$copy" :active-nav="$activeNav" :title="$title">
    <div class="space-y-5">
        <p class="text-sm text-text-secondary">{{ $course->name }}</p>

        @if ($resources->isEmpty())
            <p class="text-base text-text-secondary">{{ $copy->get('hub.no_resources') }}</p>
        @else
            <div class="grid gap-3">
                @foreach ($resources as $resource)
                    <a
                        href="{{ route('tg.resources.show', $resource) }}"
                        class="rounded-2xl border border-border-light px-4 py-3 text-left text-sm font-semibold text-text-primary"
                    >
                        @if ($resource->is_premium)
                            🔒
                        @endif
                        {{ $resource->title }}
                    </a>
                @endforeach
            </div>
        @endif

        <a href="{{ route('tg.courses.show', $course) }}" class="inline-flex w-full items-center justify-center rounded-2xl border border-border-light px-4 py-3 text-sm font-semibold">
            {{ $copy->get('hub.back') }}
        </a>
    </div>
</x-telegram.mini-app.layout>
