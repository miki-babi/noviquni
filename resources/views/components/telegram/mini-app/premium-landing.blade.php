@props([
    'copy',
    'price',
    'seasonPrice',
    'paymentBotUrl' => null,
    'required' => 0,
    'progress' => 0,
    'urgency' => null,
    'isPremium' => false,
    'resource' => null,
])

@php
    /** @var \App\Support\TelegramCopy $copy */
    $features = [
        ['icon' => 'question', 'title' => 'feature_questions_title', 'body' => 'feature_questions_body'],
        ['icon' => 'list', 'title' => 'feature_quizzes_title', 'body' => 'feature_quizzes_body'],
        ['icon' => 'bolt', 'title' => 'feature_short_title', 'body' => 'feature_short_body'],
        ['icon' => 'timer', 'title' => 'feature_exams_title', 'body' => 'feature_exams_body'],
        ['icon' => 'shield', 'title' => 'feature_checked_title', 'body' => 'feature_checked_body'],
        ['icon' => 'calendar', 'title' => 'feature_fresh_title', 'body' => 'feature_fresh_body'],
    ];
    $reasons = [
        ['icon' => 'layers', 'body' => 'why_one'],
        ['icon' => 'target', 'body' => 'why_two'],
        ['icon' => 'bolt', 'body' => 'why_three'],
    ];
@endphp

<div class="premium-landing space-y-6 pb-2">
    @if ($resource)
        <div class="premium-landing__locked-chip">
            <span class="premium-landing__locked-label">{{ $copy->get('premium_landing.locked_label') }}</span>
            <p class="mt-1 text-sm font-semibold text-text-primary">{{ $resource->title }}</p>
            @if (filled($resource->description))
                <p class="mt-1 text-xs leading-relaxed text-text-secondary">{{ \Illuminate\Support\Str::limit($resource->description, 140) }}</p>
            @endif
        </div>
    @endif

    @if ($isPremium)
        <div class="premium-landing__active">
            <p class="text-base font-bold text-text-primary">{{ $copy->get('premium_landing.active_title') }}</p>
            <p class="mt-1 text-sm leading-relaxed text-text-secondary">{{ $copy->get('premium_landing.active_body') }}</p>
        </div>
    @else
        <section class="premium-landing__hero">
            <div class="flex flex-wrap items-center gap-2">
                <span class="premium-landing__brand">{{ config('app.name') }}</span>
                <span class="premium-landing__badge">{{ $copy->get('premium_landing.badge') }}</span>
            </div>
            <h2 class="premium-landing__headline">{{ $copy->get('premium_landing.headline') }}</h2>
            <p class="mt-3 text-sm leading-relaxed text-text-secondary">{{ $copy->get('premium_landing.intro') }}</p>
            <p class="premium-landing__hero-note mt-4">{{ $copy->get('premium_landing.hero_note') }}</p>
            <div class="premium-landing__uni-row mt-4">
                <span>AAU</span>
                <span>Hawassa</span>
                <span>Wollega</span>
                <span>MTU</span>
            </div>
        </section>

        <section>
            <h3 class="premium-landing__section-title">{{ $copy->get('premium_landing.what_you_get') }}</h3>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($features as $feature)
                    <article class="premium-landing__feature">
                        <div class="premium-landing__icon" aria-hidden="true">
                            <x-telegram.mini-app.premium-icon :name="$feature['icon']" />
                        </div>
                        <h4 class="mt-2 text-sm font-bold text-text-primary">{{ $copy->get('premium_landing.'.$feature['title']) }}</h4>
                        <p class="mt-1 text-xs leading-relaxed text-text-secondary">{{ $copy->get('premium_landing.'.$feature['body']) }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section>
            <h3 class="premium-landing__section-title">{{ $copy->get('premium_landing.why_premium') }}</h3>
            <div class="mt-3 grid grid-cols-1 gap-3">
                @foreach ($reasons as $reason)
                    <div class="premium-landing__why-item">
                        <div class="premium-landing__icon premium-landing__icon--sm" aria-hidden="true">
                            <x-telegram.mini-app.premium-icon :name="$reason['icon']" />
                        </div>
                        <p class="text-sm leading-relaxed text-text-secondary">{{ $copy->get('premium_landing.'.$reason['body']) }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="premium-landing__pricing">
            <div class="flex items-center gap-2">
                <span class="premium-landing__crown" aria-hidden="true">♛</span>
                <h3 class="text-base font-bold text-white">{{ $copy->get('premium_landing.pricing_title') }}</h3>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="premium-landing__price-card">
                    <p class="text-xs font-semibold text-text-secondary">{{ $copy->get('premium_landing.monthly') }}</p>
                    <p class="mt-2 text-xl font-extrabold tracking-tight text-text-primary">ETB {{ number_format($price, 0) }}</p>
                    <p class="mt-1 text-xs text-text-secondary">{{ $copy->get('premium_landing.per_month') }}</p>
                </div>
                <div class="premium-landing__price-card">
                    <p class="text-xs font-semibold text-text-secondary">{{ $copy->get('premium_landing.exam_season') }}</p>
                    <p class="mt-2 text-xl font-extrabold tracking-tight text-text-primary">ETB {{ number_format($seasonPrice, 0) }}</p>
                    <p class="mt-1 text-xs text-text-secondary">{{ $copy->get('premium_landing.whole_season') }}</p>
                </div>
            </div>

            @if (filled($urgency))
                <p class="mt-3 text-xs font-medium leading-relaxed text-white/85">{{ $urgency }}</p>
            @endif

            @if ($required > 0)
                <p class="mt-2 text-xs text-white/80">
                    {{ $copy->get('premium_landing.referral_progress', ['progress' => $progress, 'required' => $required]) }}
                </p>
            @endif

            @if ($paymentBotUrl)
                <a
                    href="{{ $paymentBotUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="premium-landing__cta mt-4"
                >
                    <span class="block text-base font-bold">{{ $copy->get('premium_landing.cta') }} →</span>
                    <span class="mt-0.5 block text-xs font-medium opacity-90">{{ $copy->get('premium_landing.cta_sub') }}</span>
                </a>
            @else
                <p class="mt-4 text-sm font-semibold text-white">{{ $copy->get('premium_landing.unavailable') }}</p>
            @endif
        </section>

        <footer class="premium-landing__footer">
            <span class="text-xs font-bold text-text-primary">{{ config('app.name') }}</span>
            <div class="flex items-center gap-2 text-[11px] font-semibold text-text-secondary">
                <span>{{ $copy->get('premium_landing.footer_study') }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ $copy->get('premium_landing.footer_practice') }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ $copy->get('premium_landing.footer_succeed') }}</span>
            </div>
        </footer>
    @endif
</div>
