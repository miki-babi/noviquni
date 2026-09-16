<x-layouts.public :seo="$seo" :breadcrumbs="$breadcrumbs" :json-ld="$jsonLd">
    <article class="mx-auto max-w-[1280px] space-y-10 px-4 py-10 sm:px-8 lg:px-16">
        <header class="space-y-4">
            <h1 class="text-3xl font-extrabold tracking-tight text-text-primary sm:text-4xl">{{ $stream->name }} stream</h1>
            <p class="max-w-2xl text-text-secondary">
                Freshman courses and resources for the {{ $stream->name }} stream.
            </p>
            <x-cta.telegram :label="'Get '.$stream->name.' resources'" payload="web" />
        </header>

        @if (filled($stream->seo_content))
            <section class="max-w-3xl text-text-secondary">{!! nl2br(e($stream->seo_content)) !!}</section>
        @endif

        <section class="space-y-4">
            <h2 class="text-2xl font-bold text-text-primary">Courses</h2>
            <ul class="grid gap-3 sm:grid-cols-2">
                @forelse ($stream->courses as $course)
                    <li>
                        <a href="{{ route('courses.show', $course) }}" class="block">
                            <x-ui.card class="transition hover:-translate-y-0.5">
                                <span class="font-semibold text-text-primary">{{ $course->name }}</span>
                            </x-ui.card>
                        </a>
                    </li>
                @empty
                    <li class="text-text-secondary">No active courses in this stream yet.</li>
                @endforelse
            </ul>
        </section>
    </article>
</x-layouts.public>
