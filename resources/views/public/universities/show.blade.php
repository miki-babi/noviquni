<x-layouts.public :seo="$seo" :breadcrumbs="$breadcrumbs" :json-ld="$jsonLd">
    <article class="mx-auto max-w-[1280px] space-y-10 px-4 py-10 sm:px-8 lg:px-16">
        <header class="space-y-4">
            <h1 class="text-3xl font-extrabold tracking-tight text-text-primary sm:text-4xl">{{ $university->name }}</h1>
            @if ($university->location)
                <p class="text-text-secondary">{{ $university->location }}</p>
            @endif
            @if ($university->description)
                <p class="max-w-3xl text-text-secondary">{{ $university->description }}</p>
            @endif
            <div class="flex flex-wrap gap-3">
                <x-cta.telegram :label="'Open '.$university->name.' resources'" payload="web" />
                @if ($university->website)
                    <a href="{{ $university->website }}" class="inline-flex items-center rounded-pill border border-border-light px-5 py-2.5 text-sm font-semibold text-text-primary hover:border-primary-200" rel="noopener noreferrer" target="_blank">
                        Official website
                    </a>
                @endif
            </div>
        </header>

        <x-ui.card class="!bg-surface-gray !border-transparent !shadow-none">
            <h2 class="text-lg font-semibold text-text-primary">At a glance</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium text-text-muted">University</dt>
                    <dd class="font-semibold text-text-primary">{{ $university->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-text-muted">Published resources</dt>
                    <dd class="font-semibold text-text-primary">{{ $university->published_resources_count }}</dd>
                </div>
            </dl>
        </x-ui.card>

        @if (filled($university->seo_content))
            <section class="max-w-3xl text-text-secondary">{!! nl2br(e($university->seo_content)) !!}</section>
        @endif

        <section class="space-y-3">
            <h2 class="text-2xl font-bold text-text-primary">What resources are available?</h2>
            <p class="text-text-secondary">
                {{ $university->name }} currently has {{ $university->published_resources_count }} published freshman resources on {{ config('app.name') }}.
            </p>
        </section>

        @if ($courses->isNotEmpty())
            <section class="space-y-4">
                <h2 class="text-2xl font-bold text-text-primary">Courses with resources</h2>
                <ul class="grid gap-3 sm:grid-cols-2">
                    @foreach ($courses as $course)
                        <li>
                            <a href="{{ route('courses.show', $course) }}" class="block">
                                <x-ui.card class="transition hover:-translate-y-0.5">
                                    <span class="font-semibold text-text-primary">{{ $course->name }}</span>
                                    @if ($course->stream)
                                        <span class="mt-1 block text-sm text-text-secondary">{{ $course->stream->name }}</span>
                                    @endif
                                </x-ui.card>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="space-y-4">
            <h2 class="text-2xl font-bold text-text-primary">Freshman resources</h2>
            <ul class="space-y-3">
                @forelse ($resources as $resource)
                    <li>
                        <a href="{{ route('resources.show', $resource) }}" class="block">
                            <x-ui.card class="transition hover:-translate-y-0.5">
                                <span class="font-semibold text-text-primary">{{ $resource->title }}</span>
                                <span class="mt-1 block text-sm text-text-secondary">
                                    {{ $resource->type->label() }}
                                    @if ($resource->course) · {{ $resource->course->name }} @endif
                                </span>
                            </x-ui.card>
                        </a>
                    </li>
                @empty
                    <li class="text-text-secondary">No published resources for this university yet.</li>
                @endforelse
            </ul>
            {{ $resources->links() }}
        </section>
    </article>
</x-layouts.public>
