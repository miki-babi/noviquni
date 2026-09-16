<x-telegram.mini-app.layout :copy="$copy" :active-nav="$activeNav" :title="$course->name">
    @if ($hubs->isEmpty())
        <div class="space-y-5">
            <p class="text-base leading-relaxed text-text-secondary">
                {!! nl2br(e($copy->get('browse.course_coming_soon', ['course' => $course->name]))) !!}
            </p>
            <form method="POST" action="{{ route('tg.profile.notifications.enable') }}">
                @csrf
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white">
                    {{ $copy->get('browse.notify') }}
                </button>
            </form>
            <a href="{{ route('tg.browse') }}" class="inline-flex w-full items-center justify-center rounded-2xl border border-border-light px-4 py-3 text-sm font-semibold">
                {{ $copy->get('hub.back') }}
            </a>
        </div>
    @else
        <div class="space-y-5">
            <p class="text-base text-text-secondary">{!! $copy->get('hub.prompt', ['course' => e($course->name)]) !!}</p>
            <div class="grid gap-3">
                @foreach ($hubs as $item)
                    <a
                        href="{{ route('tg.courses.hub', ['course' => $course, 'hub' => $item['hub']->value]) }}"
                        class="rounded-2xl border border-border-light px-4 py-3 text-left text-sm font-semibold text-text-primary"
                    >
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
            <a href="{{ route('tg.browse') }}" class="inline-flex w-full items-center justify-center rounded-2xl border border-border-light px-4 py-3 text-sm font-semibold">
                {{ $copy->get('hub.back') }}
            </a>
        </div>
    @endif
</x-telegram.mini-app.layout>
