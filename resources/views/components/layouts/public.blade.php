@props([
    'seo',
    'breadcrumbs' => null,
    'jsonLd' => [],
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-seo.meta :meta="$seo" />
    <x-seo.json-ld :graph="$jsonLd" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-surface-white text-text-primary antialiased">
    <header class="sticky top-0 z-40 border-b border-border-light/60 bg-surface-white/95 backdrop-blur">
        <div class="mx-auto grid max-w-[1280px] grid-cols-[1fr_auto_1fr] items-center gap-4 px-4 py-5 sm:px-8 lg:px-16">
            <nav class="hidden items-center gap-5 text-[13px] font-medium text-text-secondary md:flex">
                <a href="{{ route('universities.index') }}" class="hover:text-text-primary">Universities</a>
                <a href="{{ route('courses.index') }}" class="hover:text-text-primary">Courses</a>
                <a href="{{ route('resources.index') }}" class="hover:text-text-primary">Resources</a>
                <a href="{{ route('hubs.show', 'exams') }}" class="hover:text-text-primary">Exams</a>
            </nav>

            <a href="{{ route('home') }}" class="justify-self-center text-base font-bold tracking-tight text-text-primary sm:text-lg">
                {{ config('app.name') }}
            </a>

            <div class="flex items-center justify-end gap-3">
                <x-cta.telegram label="Open Telegram" payload="web" />
            </div>
        </div>
    </header>

    <main>
        @if ($breadcrumbs)
            <div class="mx-auto max-w-[1280px] px-4 pt-6 sm:px-8 lg:px-16">
                <x-seo.breadcrumbs :items="$breadcrumbs" />
            </div>
        @endif

        {{ $slot }}
    </main>

    <footer class="mt-20 border-t border-border-light bg-surface-gray">
        <div class="mx-auto flex max-w-[1280px] flex-col gap-8 px-4 py-12 sm:px-8 lg:flex-row lg:items-start lg:justify-between lg:px-16">
            <div class="max-w-md space-y-3">
                <p class="text-lg font-bold text-text-primary">{{ config('app.name') }}</p>
                <p class="text-sm text-text-secondary">
                    The digital companion for Ethiopian university students — modules, notes, past exams, and practice in one place.
                </p>
            </div>
            <nav class="flex flex-wrap gap-x-5 gap-y-2 text-sm font-medium text-text-secondary">
                <a href="{{ route('universities.index') }}" class="hover:text-primary-200">Universities</a>
                <a href="{{ route('courses.index') }}" class="hover:text-primary-200">Courses</a>
                <a href="{{ route('resources.index') }}" class="hover:text-primary-200">Resources</a>
                <a href="{{ route('hubs.show', 'modules') }}" class="hover:text-primary-200">Modules</a>
                <a href="{{ route('hubs.show', 'notes') }}" class="hover:text-primary-200">Notes</a>
                <a href="{{ route('hubs.show', 'exams') }}" class="hover:text-primary-200">Exams</a>
                <a href="{{ route('hubs.show', 'practice') }}" class="hover:text-primary-200">Practice</a>
            </nav>
            <x-cta.telegram label="Open Telegram" payload="web" />
        </div>
    </footer>
</body>
</html>
