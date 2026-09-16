<x-layouts.public :seo="$seo" :breadcrumbs="$breadcrumbs" :json-ld="$jsonLd">
    <div class="mx-auto max-w-[1280px] space-y-8 px-4 py-10 sm:px-8 lg:px-16">
        <div class="space-y-3">
            <h1 class="text-3xl font-extrabold tracking-tight text-text-primary sm:text-4xl">Freshman courses</h1>
            <p class="max-w-2xl text-text-secondary">
                Browse courses with modules, notes, past exams, and practice resources.
            </p>
        </div>

        <ul class="grid gap-4 sm:grid-cols-2">
            @forelse ($courses as $course)
                <li>
                    <a href="{{ route('courses.show', $course) }}" class="block">
                        <x-ui.card class="h-full transition hover:-translate-y-0.5 hover:shadow-float">
                            <h2 class="text-lg font-semibold text-text-primary">{{ $course->name }}</h2>
                            @if ($course->stream)
                                <p class="mt-2 text-sm text-text-secondary">{{ $course->stream->name }}</p>
                            @endif
                            <p class="mt-3 text-sm font-medium text-primary-200">{{ $course->published_resources_count }} resources</p>
                        </x-ui.card>
                    </a>
                </li>
            @empty
                <li class="text-text-secondary">No courses published yet.</li>
            @endforelse
        </ul>

        {{ $courses->links() }}
    </div>
</x-layouts.public>
