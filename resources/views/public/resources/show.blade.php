@php
    use App\Enums\CollegeResourceKind;

    $canStudy = $resource->canStudyOnWeb();
    $studyKind = $canStudy ? $resource->studyKind() : null;
    $studyPayload = $canStudy ? $resource->studyPayload() : null;
@endphp

<x-layouts.public :seo="$seo" :breadcrumbs="$breadcrumbs" :json-ld="$jsonLd">
    <article class="mx-auto max-w-[1280px] space-y-10 px-4 py-10 sm:px-8 lg:px-16">
        <header class="space-y-4">
            <x-ui.badge>{{ $resource->type->label() }}</x-ui.badge>
            <h1 class="text-3xl font-extrabold tracking-tight text-text-primary sm:text-4xl">{{ $resource->title }}</h1>
            @if ($resource->description)
                <p class="max-w-3xl text-lg text-text-secondary">{{ $resource->description }}</p>
            @endif
            @if ($canStudy)
                <x-cta.telegram label="Also open in Telegram" :payload="'resource_'.$resource->id" />
            @else
                <x-cta.telegram label="Open in Telegram to access" :payload="'resource_'.$resource->id" />
            @endif
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
                        @elseif ($canStudy)
                            Study on the web
                        @else
                            Available via Telegram
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

        @if ($canStudy && $studyPayload !== null)
            <section class="space-y-4">
                <h2 class="text-2xl font-bold text-text-primary">
                    @switch ($studyKind)
                        @case (CollegeResourceKind::Notes)
                            Study notes
                            @break
                        @case (CollegeResourceKind::Quiz)
                            Practice quiz
                            @break
                        @case (CollegeResourceKind::Exam)
                            Practice exam
                            @break
                        @case (CollegeResourceKind::Flashcards)
                            Flashcards
                            @break
                        @default
                            Study
                    @endswitch
                </h2>

                @switch ($studyKind)
                    @case (CollegeResourceKind::Notes)
                        <x-study.notes :payload="$studyPayload" />
                        @break
                    @case (CollegeResourceKind::Quiz)
                        <x-study.quiz :payload="$studyPayload" />
                        @break
                    @case (CollegeResourceKind::Exam)
                        <x-study.exam :payload="$studyPayload" />
                        @break
                    @case (CollegeResourceKind::Flashcards)
                        <x-study.flashcards :payload="$studyPayload" />
                        @break
                @endswitch
            </section>
        @else
            <section class="space-y-3">
                <h2 class="text-2xl font-bold text-text-primary">How do I access this resource?</h2>
                <p class="text-text-secondary">
                    Open {{ config('app.name') }} in Telegram to read this {{ strtolower($resource->type->label()) }}.
                    Public pages explain what is covered; the full generated content is delivered inside Telegram.
                </p>
                <x-cta.telegram label="Continue in Telegram" :payload="'resource_'.$resource->id" />
            </section>
        @endif

        @if ($resource->course)
            <section class="space-y-3">
                <h2 class="text-2xl font-bold text-text-primary">Related links</h2>
                <ul class="space-y-2 text-primary-200">
                    <li><a href="{{ route('courses.show', $resource->course) }}" class="hover:underline">{{ $resource->course->name }} resources</a></li>
                    <li><a href="{{ route('hubs.course', ['exams', $resource->course]) }}" class="hover:underline">{{ $resource->course->name }} past exams</a></li>
                    <li><a href="{{ route('hubs.course', ['modules', $resource->course]) }}" class="hover:underline">{{ $resource->course->name }} modules</a></li>
                    <li><a href="{{ route('hubs.course', ['notes', $resource->course]) }}" class="hover:underline">{{ $resource->course->name }} notes</a></li>
                    <li><a href="{{ route('hubs.course', ['practice', $resource->course]) }}" class="hover:underline">{{ $resource->course->name }} practice</a></li>
                </ul>
            </section>
        @endif

        @if ($related->isNotEmpty())
            <section class="space-y-4">
                <h2 class="text-2xl font-bold text-text-primary">Related resources</h2>
                <ul class="space-y-3">
                    @foreach ($related as $item)
                        <li>
                            <a href="{{ route('resources.show', $item) }}" class="block">
                                <x-ui.card class="transition hover:-translate-y-0.5">
                                    <span class="font-semibold text-text-primary">{{ $item->title }}</span>
                                    <span class="mt-1 block text-sm text-text-secondary">{{ $item->type->label() }}</span>
                                </x-ui.card>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </article>
</x-layouts.public>
