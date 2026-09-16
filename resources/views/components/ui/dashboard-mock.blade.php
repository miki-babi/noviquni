@props([
    'moduleCount' => 0,
    'noteCount' => 0,
    'examCount' => 0,
])

<div class="overflow-hidden rounded-[24px] border border-border-light bg-surface-white shadow-float">
    <div class="flex items-center justify-between gap-3 border-b border-border-light bg-surface-white px-4 py-3">
        <div class="flex items-center gap-2">
            <span class="inline-flex h-8 items-center gap-2 rounded-lg bg-primary-200 px-2.5 text-xs font-bold text-white">
                {{ config('app.name') }}
            </span>
            <div class="hidden sm:block">
                <p class="text-sm font-semibold text-text-primary">Good morning, freshman</p>
                <p class="text-xs text-text-muted">Here's what's waiting in your library today.</p>
            </div>
        </div>
        <div class="hidden rounded-pill border border-border-light px-3 py-1.5 text-xs text-text-muted md:block">
            Search resources...
        </div>
    </div>

    <div class="grid md:grid-cols-[180px_1fr]">
        <aside class="hidden border-r border-border-light bg-surface-gray/60 p-3 md:block">
            <ul class="space-y-1 text-xs font-medium text-text-secondary">
                <li class="rounded-pill bg-primary-50 px-3 py-2 text-primary-300">Library</li>
                <li class="px-3 py-2">Modules</li>
                <li class="px-3 py-2">Notes</li>
                <li class="px-3 py-2">Exams</li>
                <li class="px-3 py-2">Practice</li>
                <li class="px-3 py-2">Courses</li>
            </ul>
            <div class="mt-6 rounded-card bg-primary-50 p-3">
                <p class="text-xs font-semibold text-text-primary">Open on Telegram</p>
                <p class="mt-1 text-[11px] text-text-secondary">Get your personalized resource library.</p>
            </div>
        </aside>

        <div class="space-y-4 p-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-card border border-border-light bg-surface-white p-3">
                    <p class="text-[11px] font-medium text-text-muted">Modules</p>
                    <p class="mt-1 text-2xl font-extrabold text-text-primary">{{ $moduleCount }}</p>
                    <p class="mt-1 text-[11px] font-medium text-primary-100">▲ published</p>
                </div>
                <div class="rounded-card border border-border-light bg-surface-white p-3">
                    <p class="text-[11px] font-medium text-text-muted">Notes</p>
                    <p class="mt-1 text-2xl font-extrabold text-text-primary">{{ $noteCount }}</p>
                    <p class="mt-1 text-[11px] font-medium text-primary-100">▲ ready to study</p>
                </div>
                <div class="rounded-card border border-border-light bg-surface-white p-3">
                    <p class="text-[11px] font-medium text-text-muted">Past exams</p>
                    <p class="mt-1 text-2xl font-extrabold text-text-primary">{{ $examCount }}</p>
                    <p class="mt-1 text-[11px] font-medium text-primary-100">▲ practice smarter</p>
                </div>
            </div>

            <div class="grid gap-3 lg:grid-cols-2">
                <div class="rounded-card border border-border-light p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-sm font-semibold">Course overview</p>
                        <span class="text-[11px] text-primary-200">View all</span>
                    </div>
                    <div class="space-y-2 text-xs text-text-secondary">
                        <div class="flex items-center justify-between gap-2">
                            <span>Mathematics</span>
                            <div class="h-1.5 w-24 overflow-hidden rounded-full bg-surface-gray">
                                <div class="h-full w-4/5 rounded-full bg-primary-200"></div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span>Physics</span>
                            <div class="h-1.5 w-24 overflow-hidden rounded-full bg-surface-gray">
                                <div class="h-full w-3/5 rounded-full bg-primary-200"></div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span>Chemistry</span>
                            <div class="h-1.5 w-24 overflow-hidden rounded-full bg-surface-gray">
                                <div class="h-full w-2/3 rounded-full bg-primary-200"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-card border border-border-light p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-sm font-semibold">Recent resources</p>
                        <span class="text-[11px] text-primary-200">View all</span>
                    </div>
                    <ul class="space-y-2 text-xs text-text-secondary">
                        <li class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-sm bg-primary-200"></span>
                            Freshman Math Module 1
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-sm bg-star-gold"></span>
                            Physics lecture notes
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-sm bg-primary-100"></span>
                            Chemistry mid exam pack
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
