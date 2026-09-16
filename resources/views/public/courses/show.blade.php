<x-layouts.public :seo="$seo" :breadcrumbs="$breadcrumbs" :json-ld="$jsonLd">
    <article class="mx-auto max-w-[1280px] space-y-10 px-4 py-10 sm:px-8 lg:px-16">
        <header class="space-y-4">
            <h1 class="text-3xl font-extrabold tracking-tight text-text-primary sm:text-4xl">Freshman {{ $course->name }} resources</h1>
            @if ($course->stream)
                <p class="text-text-secondary">
                    Stream:
                    <a href="{{ route('streams.show', $course->stream) }}" class="font-medium text-primary-200 hover:underline">{{ $course->stream->name }}</a>
                </p>
            @endif
            <x-cta.telegram :label="'Open '.$course->name.' in Telegram'" :payload="'course_'.$course->slug" />
        </header>

        <x-ui.card class="!bg-surface-gray !border-transparent !shadow-none">
            <h2 class="text-lg font-semibold text-text-primary">At a glance</h2>
            <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-xs font-medium text-text-muted">Modules</dt>
                    <dd class="text-2xl font-extrabold text-text-primary">{{ $counts['modules'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-text-muted">Notes</dt>
                    <dd class="text-2xl font-extrabold text-text-primary">{{ $counts['notes'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-text-muted">Past exams</dt>
                    <dd class="text-2xl font-extrabold text-text-primary">{{ $counts['exams'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-text-muted">Practice</dt>
                    <dd class="text-2xl font-extrabold text-text-primary">{{ $counts['practice'] }}</dd>
                </div>
            </dl>
        </x-ui.card>

        <section class="space-y-5">
            <h2 class="text-2xl font-bold text-text-primary">Common questions</h2>
            <div class="space-y-5">
                <div>
                    <h3 class="font-semibold text-text-primary">What is freshman {{ $course->name }}?</h3>
                    <p class="mt-2 text-text-secondary">
                        Freshman {{ $course->name }} is a core course for Ethiopian university students
                        @if ($course->stream) in the {{ $course->stream->name }} stream @endif.
                        {{ config('app.name') }} collects modules, notes, past exams, and practice materials for it.
                    </p>
                </div>
                <div>
                    <h3 class="font-semibold text-text-primary">Where can I find {{ $course->name }} modules?</h3>
                    <p class="mt-2 text-text-secondary">
                        There {{ $counts['modules'] === 1 ? 'is' : 'are' }} {{ $counts['modules'] }} published
                        <a href="{{ route('hubs.course', ['modules', $course]) }}" class="font-medium text-primary-200 hover:underline">{{ strtolower(\App\Enums\ResourceHub::Modules->label()) }}</a>
                        for this course.
                    </p>
                </div>
                <div>
                    <h3 class="font-semibold text-text-primary">Where can I find past exams?</h3>
                    <p class="mt-2 text-text-secondary">
                        Browse
                        <a href="{{ route('hubs.course', ['exams', $course]) }}" class="font-medium text-primary-200 hover:underline">{{ $course->name }} past exams</a>
                        or open the full library in Telegram.
                    </p>
                </div>
            </div>
        </section>

        @if (filled($course->seo_content))
            <section class="max-w-3xl text-text-secondary">{!! nl2br(e($course->seo_content)) !!}</section>
        @endif

        <section class="space-y-4">
            <h2 class="text-2xl font-bold text-text-primary">Browse by type</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($hubs as $hub)
                    <a href="{{ route('hubs.course', [$hub->value, $course]) }}" class="block">
                        <x-ui.card class="transition hover:-translate-y-0.5">
                            <span class="font-semibold text-text-primary">{{ $hub->label() }}</span>
                        </x-ui.card>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-2xl font-bold text-text-primary">All published resources</h2>
            <ul class="space-y-3">
                @forelse ($resources as $resource)
                    <li>
                        <a href="{{ route('resources.show', $resource) }}" class="block">
                            <x-ui.card class="transition hover:-translate-y-0.5">
                                <span class="font-semibold text-text-primary">{{ $resource->title }}</span>
                                <span class="mt-1 block text-sm text-text-secondary">{{ $resource->type->label() }}</span>
                            </x-ui.card>
                        </a>
                    </li>
                @empty
                    <li class="text-text-secondary">No published resources for this course yet.</li>
                @endforelse
            </ul>
        </section>
    </article>
</x-layouts.public>
