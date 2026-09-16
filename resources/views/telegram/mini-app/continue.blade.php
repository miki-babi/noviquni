<x-telegram.mini-app.layout :copy="$copy" :active-nav="$activeNav" :title="$copy->get('keyboard.continue')">
    <div class="space-y-5">
        <div class="space-y-2 text-base leading-relaxed text-text-secondary">
            {!! nl2br($copy->get('continue.title', [
                'course' => e($course?->name ?? 'Course'),
                'resource' => e($resource->title),
            ])) !!}
        </div>

        <div class="grid gap-3">
            <a
                href="{{ route('tg.resources.show', $resource) }}"
                class="inline-flex items-center justify-center rounded-2xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white"
            >
                {{ $copy->get('continue.resume') }}
            </a>
            <a
                href="{{ route('tg.browse') }}"
                class="inline-flex items-center justify-center rounded-2xl border border-border-light px-4 py-3 text-sm font-semibold text-text-primary"
            >
                {{ $copy->get('continue.switch') }}
            </a>
        </div>
    </div>
</x-telegram.mini-app.layout>
