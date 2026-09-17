<x-telegram.mini-app.layout :copy="$copy" :active-nav="$activeNav" :title="$course->name">
    <div class="space-y-6">
        <p class="text-base text-text-secondary">
            Study path for {{ $course->name }} — module → notes → worksheet → quiz + flashcards → past exams.
        </p>

        @if ($plans->isNotEmpty())
            <section class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-text-muted">Plans</h2>
                @foreach ($plans as $plan)
                    <div class="rounded-2xl border border-border-light px-4 py-3">
                        <p class="text-sm font-semibold text-text-primary">{{ $plan->title }}</p>
                        @if ($plan->description)
                            <p class="mt-1 text-xs text-text-secondary">{{ $plan->description }}</p>
                        @endif
                        <ul class="mt-3 space-y-2">
                            @foreach ($plan->items as $item)
                                <li class="text-sm text-text-secondary">
                                    @if ($item->learningResource)
                                        <a href="{{ $item->learningResource->miniAppUrl() }}" class="font-medium text-primary-600">
                                            {{ $item->label }}
                                        </a>
                                    @else
                                        {{ $item->label }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </section>
        @endif

        @if ($steps->isEmpty())
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
            </div>
        @else
            <section class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-text-muted">Path</h2>
                <div class="grid gap-2">
                    @foreach ($steps as $item)
                        @php
                            $resource = $item['resource'];
                            $locked = $item['locked'];
                            $completed = $item['completed'];
                        @endphp
                        <a
                            href="{{ $resource->miniAppUrl() }}"
                            class="flex items-center justify-between rounded-2xl border border-border-light px-4 py-3 text-left text-sm {{ $locked ? 'opacity-70' : '' }}"
                        >
                            <span class="font-semibold text-text-primary">
                                @if ($completed)✓ @endif
                                @if ($locked)🔒 @endif
                                {{ $resource->title }}
                            </span>
                            <span class="text-xs text-text-muted">{{ $resource->type->label() }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($isPremium && $archiveHubs->isNotEmpty())
            <section class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-text-muted">Archive</h2>
                <div class="grid gap-2">
                    @foreach ($archiveHubs as $item)
                        <a
                            href="{{ route('tg.courses.hub', ['course' => $course, 'hub' => $item['hub']->value]) }}"
                            class="rounded-2xl border border-border-light px-4 py-3 text-left text-sm font-semibold text-text-primary"
                        >
                            {{ $item['label'] }} ({{ $item['count'] }})
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <a href="{{ route('tg.browse') }}" class="inline-flex w-full items-center justify-center rounded-2xl border border-border-light px-4 py-3 text-sm font-semibold">
            {{ $copy->get('hub.back') }}
        </a>
    </div>
</x-telegram.mini-app.layout>
