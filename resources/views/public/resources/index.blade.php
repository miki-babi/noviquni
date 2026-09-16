<x-layouts.public :seo="$seo" :breadcrumbs="$breadcrumbs" :json-ld="$jsonLd">
    <div class="mx-auto max-w-[1280px] space-y-8 px-4 py-10 sm:px-8 lg:px-16">
        <div class="space-y-3">
            <h1 class="text-3xl font-extrabold tracking-tight text-text-primary sm:text-4xl">Learning resources</h1>
            <p class="max-w-2xl text-text-secondary">
                Published freshman modules, notes, past exams, and practice sets.
            </p>
        </div>

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
                <li class="text-text-secondary">No published resources yet.</li>
            @endforelse
        </ul>

        {{ $resources->links() }}
    </div>
</x-layouts.public>
