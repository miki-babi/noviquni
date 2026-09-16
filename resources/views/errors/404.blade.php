<x-layouts.public
    :seo="[
        'title' => 'Page not found | '.config('app.name'),
        'description' => 'The page you requested could not be found.',
        'canonical' => url()->current(),
        'og_title' => 'Page not found | '.config('app.name'),
        'og_description' => 'The page you requested could not be found.',
        'og_image' => null,
        'og_type' => 'website',
        'robots' => 'noindex,follow',
        'twitter_card' => 'summary',
    ]"
    :breadcrumbs="[
        ['name' => 'Home', 'url' => route('home')],
        ['name' => 'Not found', 'url' => null],
    ]"
>
    <div class="mx-auto max-w-[1280px] space-y-6 px-4 py-20 sm:px-8 lg:px-16">
        <x-ui.badge>404</x-ui.badge>
        <h1 class="text-3xl font-extrabold tracking-tight text-text-primary sm:text-4xl">Page not found</h1>
        <p class="max-w-lg text-text-secondary">
            The page you are looking for does not exist or is no longer published.
        </p>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('home') }}" class="inline-flex items-center rounded-pill border border-border-light px-5 py-2.5 text-sm font-semibold text-text-primary hover:border-primary-200">
                Go home
            </a>
            <x-cta.telegram label="Open Telegram" payload="web" />
        </div>
    </div>
</x-layouts.public>
