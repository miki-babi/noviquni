<x-layouts.public :seo="$seo" :breadcrumbs="$breadcrumbs" :json-ld="$jsonLd">
    <div class="mx-auto max-w-[1280px] space-y-8 px-4 py-10 sm:px-8 lg:px-16">
        <div class="space-y-3">
            <h1 class="text-3xl font-extrabold tracking-tight text-text-primary sm:text-4xl">
                @if ($course)
                    Freshman {{ $course->name }} {{ $hub->label() }}
                @else
                    Freshman {{ $hub->label() }}
                @endif
            </h1>
            <p class="max-w-2xl text-text-secondary">
                @if ($course)
                    Published {{ strtolower($hub->label()) }} for freshman {{ $course->name }}.
                @else
                    Browse freshman {{ strtolower($hub->label()) }} for Ethiopian university students.
                @endif
            </p>
            <x-cta.telegram
                :label="$course ? 'Open '.$course->name.' in Telegram' : 'Open Telegram'"
                :payload="$course ? 'course_'.$course->slug : 'web'"
            />
        </div>

        @if ($course)
            <p class="text-sm text-text-secondary">
                See all
                <a href="{{ route('courses.show', $course) }}" class="font-medium text-primary-200 hover:underline">{{ $course->name }} resources</a>.
            </p>
        @endif

        <ul class="space-y-3">
            @forelse ($resources as $resource)
                <li>
                    <a href="{{ route('resources.show', $resource) }}" class="block">
                        <x-ui.card class="transition hover:-translate-y-0.5">
                            <h2 class="font-semibold text-text-primary">{{ $resource->title }}</h2>
                            <p class="mt-1 text-sm text-text-secondary">
                                {{ $resource->type->label() }}
                                @if ($resource->course) · {{ $resource->course->name }} @endif
                                @if ($resource->university) · {{ $resource->university->name }} @endif
                            </p>
                        </x-ui.card>
                    </a>
                </li>
            @empty
                <li class="text-text-secondary">No published {{ strtolower($hub->label()) }} yet.</li>
            @endforelse
        </ul>

        {{ $resources->links() }}
    </div>
</x-layouts.public>
