@props([
    'copy',
    'price',
    'paymentBotUrl' => null,
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
    $formattedPrice = 'ETB '.number_format((float) $price, 0);
@endphp

<div @class(['premium-landing', 'premium-landing--with-bar' => ! $isPremium])>
    @if ($resource)
        <div class="premium-landing__locked-chip">
            <span class="premium-landing__locked-label">{{ $copy->get('premium_landing.locked_label') }}</span>
            <p class="mt-1 text-sm font-semibold text-text-primary">{{ $resource->title }}</p>
        </div>
    @endif

    @if ($isPremium)
        <div class="premium-landing__active">
            <p class="text-base font-bold text-text-primary">{{ $copy->get('premium_landing.active_title') }}</p>
            <p class="mt-1 text-sm leading-relaxed text-text-secondary">{{ $copy->get('premium_landing.active_body') }}</p>
        </div>
    @else
        <header class="premium-landing__header">
            <div class="premium-landing__header-top">
                <span class="premium-landing__brand">{{ config('app.name') }}</span>
                <span class="premium-landing__badge">{{ $copy->get('premium_landing.badge') }}</span>
            </div>
            <h2 class="premium-landing__title">{{ $copy->get('premium_landing.title') }}</h2>
            <p class="premium-landing__subtitle">{{ $copy->get('premium_landing.subtitle') }}</p>
        </header>

        <ul class="premium-landing__list" role="list">
            @foreach ($features as $feature)
                <li class="premium-landing__row">
                    <div class="premium-landing__icon" aria-hidden="true">
                        <x-telegram.mini-app.premium-icon :name="$feature['icon']" />
                    </div>
                    <div class="premium-landing__row-body min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <h3 class="premium-landing__row-title">{{ $copy->get('premium_landing.'.$feature['title']) }}</h3>
                            <span class="premium-landing__pill premium-landing__pill--bot">{{ $copy->get('premium_landing.badge_bot') }}</span>
                        </div>
                        <p class="premium-landing__row-desc">{{ $copy->get('premium_landing.'.$feature['body']) }}</p>
                    </div>
                    <span class="premium-landing__check" aria-hidden="true">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143z" clip-rule="evenodd" />
                        </svg>
                    </span>
                </li>
            @endforeach
        </ul>

        <div class="premium-landing__plan">
            <div class="premium-landing__plan-card premium-landing__plan-card--selected" aria-current="true">
                <p class="premium-landing__plan-label">{{ $copy->get('premium_landing.one_time') }}</p>
                <p class="premium-landing__plan-amount">{{ $formattedPrice }}</p>
            </div>
        </div>

        <div class="premium-landing__bar">
            <div class="min-w-0">
                <p class="premium-landing__bar-price">{{ $formattedPrice }}</p>
                <p class="premium-landing__bar-meta">{{ $copy->get('premium_landing.plan_summary') }}</p>
            </div>
            @if ($paymentBotUrl)
                <a
                    href="{{ $paymentBotUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="premium-landing__bar-cta"
                >
                    {{ $copy->get('premium_landing.cta') }}
                </a>
            @else
                <p class="premium-landing__bar-unavailable">{{ $copy->get('premium_landing.unavailable') }}</p>
            @endif
        </div>
    @endif
</div>
