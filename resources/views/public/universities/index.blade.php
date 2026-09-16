<x-layouts.public :seo="$seo" :breadcrumbs="$breadcrumbs" :json-ld="$jsonLd">
    <div class="mx-auto max-w-[1280px] space-y-8 px-4 py-10 sm:px-8 lg:px-16">
        <div class="space-y-3">
            <h1 class="text-3xl font-extrabold tracking-tight text-text-primary sm:text-4xl">Ethiopian universities</h1>
            <p class="max-w-2xl text-text-secondary">
                University profiles with freshman resources, courses, modules, and past exams.
            </p>
        </div>

        <ul class="grid gap-4 sm:grid-cols-2">
            @forelse ($universities as $university)
                <li>
                    <a href="{{ route('universities.show', $university) }}" class="block">
                        <x-ui.card class="h-full transition hover:-translate-y-0.5 hover:shadow-float">
                            <h2 class="text-lg font-semibold text-text-primary">{{ $university->name }}</h2>
                            @if ($university->location)
                                <p class="mt-2 text-sm text-text-secondary">{{ $university->location }}</p>
                            @endif
                            <p class="mt-3 text-sm font-medium text-primary-200">{{ $university->published_resources_count }} published resources</p>
                        </x-ui.card>
                    </a>
                </li>
            @empty
                <li class="text-text-secondary">No universities published yet.</li>
            @endforelse
        </ul>
    </div>
</x-layouts.public>
