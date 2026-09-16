<x-layouts.public :seo="$seo" :breadcrumbs="null" :json-ld="$jsonLd">
    {{-- Hero --}}
    <section class="mx-auto max-w-[1280px] px-4 py-12 sm:px-8 lg:px-16 lg:py-16">
        <div class="grid items-center gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:gap-10">
            <div class="space-y-6">
                <x-ui.badge>✦ Freshman resources for Ethiopian universities</x-ui.badge>

                <h1 class="max-w-xl text-[40px] font-extrabold leading-[1.1] tracking-tight text-text-primary sm:text-[48px]">
                    Better study tools for Ethiopian university students
                </h1>

                <p class="max-w-lg text-[15px] text-text-secondary">
                    Find modules, notes, past exams, and practice sets for freshman courses — then open your personalized library in Telegram.
                </p>

                <div class="flex flex-wrap items-center gap-4">
                    <x-cta.telegram label="Open Telegram" payload="web" />
                    <a href="{{ route('courses.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-text-primary hover:text-primary-200">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-primary-50 text-primary-300">▶</span>
                        Browse courses
                    </a>
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <div class="flex -space-x-2">
                        @foreach (['#059467', '#FBBF24', '#0BD294', '#6B7280', '#BDFFDE'] as $color)
                            <span class="inline-block h-8 w-8 rounded-full border-2 border-white" style="background: {{ $color }}"></span>
                        @endforeach
                    </div>
                    <div>
                        <div class="flex gap-0.5 text-star-gold" aria-hidden="true">★★★★★</div>
                        <p class="text-xs font-medium text-text-secondary">
                            Trusted by Ethiopian freshman students<br>
                            discovering resources every week.
                        </p>
                    </div>
                </div>
            </div>

            <x-ui.dashboard-mock
                :module-count="$stats['modules']"
                :note-count="$stats['notes']"
                :exam-count="$stats['exams']"
            />
        </div>
    </section>

    {{-- Trust bar --}}
    <section class="border-y border-border-light bg-surface-white">
        <div class="mx-auto flex max-w-[1280px] flex-col gap-6 px-4 py-8 sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-16">
            <p class="text-sm font-medium text-text-secondary">Used by students across Ethiopia</p>
            <div class="flex flex-wrap items-center gap-6 text-sm font-semibold text-text-muted">
                @forelse ($universities->take(3) as $university)
                    <span>{{ $university->name }}</span>
                @empty
                    <span>Addis Ababa University</span>
                    <span>Jimma University</span>
                    <span>Bahir Dar University</span>
                @endforelse
            </div>
        </div>
    </section>

    {{-- Mission --}}
    <section class="mx-auto max-w-[1280px] px-4 py-20 sm:px-8 lg:px-16">
        <div class="grid gap-8 lg:grid-cols-[auto_1fr] lg:items-start">
            <x-ui.badge>{{ config('app.name') }}</x-ui.badge>
            <p class="max-w-3xl text-[28px] font-medium leading-snug sm:text-[32px]">
                <span class="font-bold text-text-primary">A connected platform</span>
                <span class="text-text-secondary"> built to make freshman study life </span>
                <span aria-hidden="true">🎯</span>
                <span class="font-bold text-text-primary"> more organized, accessible,</span>
                <span class="text-text-secondary"> and </span>
                <span aria-hidden="true">🔶</span>
                <span class="font-bold text-text-primary"> informed.</span>
            </p>
        </div>
    </section>

    {{-- Stats --}}
    <section class="mx-auto max-w-[1280px] px-4 pb-20 sm:px-8 lg:px-16">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="flex min-h-[220px] flex-col justify-between rounded-card bg-surface-gray p-6">
                <div class="flex flex-wrap gap-2">
                    @foreach (range(1, 8) as $i)
                        <span class="h-8 w-8 rounded-full bg-border-light"></span>
                    @endforeach
                </div>
                <div>
                    <p class="text-[30px] font-extrabold text-text-primary">{{ number_format($stats['universities']) }}+</p>
                    <p class="mt-1 font-semibold text-text-primary">Universities listed</p>
                    <p class="mt-2 text-sm text-text-secondary">Profiles with freshman resources and course links.</p>
                </div>
            </div>

            <div class="flex min-h-[220px] flex-col justify-between rounded-card bg-primary-200 p-6 text-white">
                <div></div>
                <div>
                    <p class="text-[30px] font-extrabold">{{ number_format($stats['courses']) }}+</p>
                    <p class="mt-1 font-semibold">Courses covered</p>
                    <p class="mt-2 text-sm text-white/80">Natural and social stream courses students actually search for.</p>
                </div>
            </div>

            <div class="flex min-h-[220px] flex-col justify-between rounded-card bg-surface-gray p-6">
                <div></div>
                <div>
                    <p class="text-[30px] font-extrabold text-text-primary">{{ number_format($stats['resources']) }}+</p>
                    <p class="mt-1 font-semibold text-text-primary">Published resources</p>
                    <p class="mt-2 text-sm text-text-secondary">Modules, notes, exams, and practice ready to open in Telegram.</p>
                </div>
            </div>

            <div class="relative flex min-h-[220px] flex-col justify-end overflow-hidden rounded-card bg-gradient-to-br from-[#1e3a5f] to-[#0f172a] p-6 text-white">
                <div class="absolute inset-0 opacity-30" style="background: radial-gradient(circle at 70% 20%, #059467, transparent 50%)"></div>
                <div class="relative">
                    <p class="text-[30px] font-extrabold">24/7</p>
                    <p class="mt-1 font-semibold">Telegram access</p>
                    <p class="mt-2 text-sm text-white/80">Your library stays with you — open resources anytime on Telegram.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section class="border-t border-border-light bg-surface-white py-20">
        <div class="mx-auto max-w-[1280px] space-y-12 px-4 sm:px-8 lg:px-16">
            <x-ui.section-heading align="center" eyebrow="WHAT YOU GET">
                Manage every part of freshman study in one place
                <x-slot:subtext>
                    From university directories and course pages to modules, notes, past exams, and practice — then continue in Telegram.
                </x-slot:subtext>
            </x-ui.section-heading>

            <div class="grid gap-0 md:grid-cols-4 md:divide-x md:divide-border-light">
                @foreach ([
                    ['title' => 'Modules', 'desc' => 'Browse published freshman modules by course and stream, then open the full files in Telegram.', 'href' => route('hubs.show', 'modules')],
                    ['title' => 'Notes & summaries', 'desc' => 'Lecture notes and summaries that help you revise faster before mid and final exams.', 'href' => route('hubs.show', 'notes')],
                    ['title' => 'Past exams', 'desc' => 'Past exam packs organized by course so you can practice the questions that matter.', 'href' => route('hubs.show', 'exams')],
                    ['title' => 'Practice sets', 'desc' => 'Practice questions to check understanding and prepare with classmates via Telegram.', 'href' => route('hubs.show', 'practice')],
                ] as $feature)
                    <a href="{{ $feature['href'] }}" class="group space-y-4 px-2 py-6 md:px-6 md:py-0">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-primary-200/40 text-primary-200 transition group-hover:bg-primary-50">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3"/><path d="M5 19c1.5-3 4-4.5 7-4.5S17.5 16 19 19"/></svg>
                        </span>
                        <h3 class="text-base font-semibold text-text-primary">{{ $feature['title'] }}</h3>
                        <p class="text-sm text-text-secondary">{{ $feature['desc'] }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section
        class="mx-auto max-w-[1280px] px-4 py-20 sm:px-8 lg:px-16"
        x-data="{ step: 0, steps: [
            { title: 'Discover on the web', body: 'Browse universities, courses, and public resource pages without creating an account.', panel: 'Browse SEO pages for modules, notes, and exams.' },
            { title: 'Open Telegram', body: 'Tap Open Telegram to start the bot and continue where the public page left off.', panel: 'Deep-link into your personalized library.' },
            { title: 'Complete onboarding', body: 'Pick your stream, university, semester, and courses so resources match what you study.', panel: 'Stream → University → Courses.' },
            { title: 'Study and invite', body: 'Download resources, stay updated, and unlock more by inviting classmates.', panel: 'Referrals unlock premium access.' }
        ]}"
    >
        <x-ui.badge class="mb-6">HOW {{ strtoupper(config('app.name')) }} WORKS</x-ui.badge>

        <div class="grid gap-10 lg:grid-cols-2 lg:items-start">
            <div class="space-y-4">
                <template x-for="(item, index) in steps" :key="index">
                    <button
                        type="button"
                        class="block w-full text-left text-[28px] font-bold leading-tight transition sm:text-[32px]"
                        :class="step === index ? 'text-text-primary' : 'text-text-muted'"
                        @click="step = index"
                        x-text="item.title"
                    ></button>
                </template>
            </div>

            <div class="space-y-6">
                <div class="relative overflow-hidden rounded-[24px] bg-surface-gray p-6 shadow-soft sm:p-8">
                    <div class="absolute inset-0 bg-gradient-to-br from-primary-50 via-transparent to-transparent"></div>
                    <div class="relative rounded-card border border-border-light bg-surface-white p-5 shadow-float">
                        <div class="mb-4 flex items-center justify-between">
                            <p class="font-semibold text-text-primary">Study overview</p>
                            <span class="rounded-pill bg-surface-gray px-3 py-1 text-xs text-text-secondary">This term</span>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="rounded-xl bg-surface-gray p-3">
                                <p class="text-[11px] text-text-muted">Modules</p>
                                <p class="text-xl font-extrabold text-primary-200">{{ max($stats['modules'], 0) }}</p>
                                <p class="text-[11px] font-medium text-primary-100">▲ ready</p>
                            </div>
                            <div class="rounded-xl bg-surface-gray p-3">
                                <p class="text-[11px] text-text-muted">Notes</p>
                                <p class="text-xl font-extrabold text-primary-200">{{ max($stats['notes'], 0) }}</p>
                                <p class="text-[11px] font-medium text-primary-100">▲ saved</p>
                            </div>
                            <div class="rounded-xl bg-surface-gray p-3">
                                <p class="text-[11px] text-text-muted">Exams</p>
                                <p class="text-xl font-extrabold text-primary-200">{{ max($stats['exams'], 0) }}</p>
                                <p class="text-[11px] text-text-muted">practice</p>
                            </div>
                        </div>
                        <p class="mt-4 text-sm text-text-secondary" x-text="steps[step].panel"></p>
                    </div>
                </div>

                <div>
                    <h3 class="text-xl font-bold text-text-primary" x-text="steps[step].title"></h3>
                    <p class="mt-2 max-w-xl text-sm text-text-secondary" x-text="steps[step].body"></p>
                    <div class="mt-4">
                        <x-cta.telegram label="Open Telegram" payload="web" />
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Directory previews --}}
    @if ($courses->isNotEmpty() || $resources->isNotEmpty())
        <section class="border-t border-border-light bg-surface-gray/50 py-16">
            <div class="mx-auto max-w-[1280px] space-y-10 px-4 sm:px-8 lg:px-16">
                @if ($courses->isNotEmpty())
                    <div class="space-y-4">
                        <div class="flex items-end justify-between gap-3">
                            <h2 class="text-2xl font-bold text-text-primary">Popular courses</h2>
                            <a href="{{ route('courses.index') }}" class="text-sm font-semibold text-primary-200 hover:underline">View all</a>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($courses->take(4) as $course)
                                <a href="{{ route('courses.show', $course) }}" class="rounded-card border border-border-light bg-surface-white p-5 shadow-soft transition hover:-translate-y-0.5">
                                    <p class="font-semibold text-text-primary">{{ $course->name }}</p>
                                    @if ($course->stream)
                                        <p class="mt-1 text-sm text-text-secondary">{{ $course->stream->name }}</p>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($resources->isNotEmpty())
                    <div class="space-y-4">
                        <div class="flex items-end justify-between gap-3">
                            <h2 class="text-2xl font-bold text-text-primary">Latest resources</h2>
                            <a href="{{ route('resources.index') }}" class="text-sm font-semibold text-primary-200 hover:underline">View all</a>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($resources->take(4) as $resource)
                                <a href="{{ route('resources.show', $resource) }}" class="rounded-card border border-border-light bg-surface-white p-5 shadow-soft transition hover:-translate-y-0.5">
                                    <p class="font-semibold text-text-primary">{{ $resource->title }}</p>
                                    <p class="mt-1 text-sm text-text-secondary">
                                        {{ $resource->type->label() }}
                                        @if ($resource->course) · {{ $resource->course->name }} @endif
                                    </p>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @endif
</x-layouts.public>
