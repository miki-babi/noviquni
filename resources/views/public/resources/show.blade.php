@php
    $canStudy = false;
@endphp

<x-layouts.public :seo="$seo" :breadcrumbs="$breadcrumbs" :json-ld="$jsonLd">
    <article class="mx-auto max-w-[1280px] space-y-10 px-4 py-10 sm:px-8 lg:px-16">
        <header class="space-y-4">
            <x-ui.badge>{{ $resource->type->label() }}</x-ui.badge>
            <h1 class="text-3xl font-extrabold tracking-tight text-text-primary sm:text-4xl">{{ $resource->title }}</h1>
            @if ($resource->description)
                <p class="max-w-3xl text-lg text-text-secondary">{{ $resource->description }}</p>
            @endif
            <x-cta.telegram
                :label="$resource->is_premium ? 'Open premium resource in Telegram' : 'Open free in Telegram'"
                :payload="'resource_'.$resource->id"
            />
        </header>

        <x-ui.card class="!bg-surface-gray !border-transparent !shadow-none">
            <h2 class="text-lg font-semibold text-text-primary">Resource facts</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium text-text-muted">Type</dt>
                    <dd class="font-semibold text-text-primary">{{ $resource->type->label() }}</dd>
                </div>
                @if ($resource->course)
                    <div>
                        <dt class="text-xs font-medium text-text-muted">Course</dt>
                        <dd class="font-semibold">
                            <a href="{{ route('courses.show', $resource->course) }}" class="text-primary-200 hover:underline">{{ $resource->course->name }}</a>
                        </dd>
                    </div>
                @endif
                @if ($resource->stream)
                    <div>
                        <dt class="text-xs font-medium text-text-muted">Stream</dt>
                        <dd class="font-semibold">
                            <a href="{{ route('streams.show', $resource->stream) }}" class="text-primary-200 hover:underline">{{ $resource->stream->name }}</a>
                        </dd>
                    </div>
                @endif
                @if ($resource->university)
                    <div>
                        <dt class="text-xs font-medium text-text-muted">University</dt>
                        <dd class="font-semibold">
                            <a href="{{ route('universities.show', $resource->university) }}" class="text-primary-200 hover:underline">{{ $resource->university->name }}</a>
                        </dd>
                    </div>
                @endif
                @if ($resource->semester)
                    <div>
                        <dt class="text-xs font-medium text-text-muted">Semester</dt>
                        <dd class="font-semibold text-text-primary">{{ $resource->semester->name }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-xs font-medium text-text-muted">Access</dt>
                    <dd class="font-semibold text-text-primary">
                        @if ($resource->is_premium)
                            Premium via Telegram
                        @else
                            Free via Telegram
                        @endif
                    </dd>
                </div>
            </dl>
        </x-ui.card>

        @if (! empty($resource->topics))
            <section class="space-y-3">
                <h2 class="text-2xl font-bold text-text-primary">What is covered?</h2>
                <ol class="list-decimal space-y-1 pl-5 text-text-secondary">
                    @foreach ($resource->topics as $topic)
                        <li>{{ $topic }}</li>
                    @endforeach
                </ol>
            </section>
        @endif

        @if (filled($resource->seo_content))
            <section class="max-w-3xl text-text-secondary">{!! nl2br(e($resource->seo_content)) !!}</section>
        @endif

        <section class="space-y-3">
            <h2 class="text-2xl font-bold text-text-primary">How do I access this resource?</h2>
            <p class="text-text-secondary">
                Open {{ config('app.name') }} in Telegram to study this {{ strtolower($resource->type->label()) }}.
                Public pages explain what is covered; study materials open inside Telegram.
            </p>
            <x-cta.telegram label="Continue in Telegram" :payload="'resource_'.$resource->id" />
        </section>

        @if ($resource->course)
            <section class="space-y-3">
                <h2 class="text-2xl font-bold text-text-primary">Related links</h2>
                <ul class="list-disc space-y-1 pl-5 text-primary-200">
                    <li><a href="{{ route('courses.show', $resource->course) }}" class="hover:underline">{{ $resource->course->name }}</a></li>
                    @foreach (\App\Enums\ResourceHub::cases() as $hub)
                        <li>
                            <a href="{{ route('hubs.course', ['hub' => $hub->value, 'course' => $resource->course]) }}" class="hover:underline">
                                {{ $hub->label() }} for {{ $resource->course->name }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </article>
</x-layouts.public>
