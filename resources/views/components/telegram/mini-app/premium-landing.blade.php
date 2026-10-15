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
            <div class="premium-landing__locked-chip-inner">
                <div class="premium-landing__lock-icon" aria-hidden="true">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="premium-landing__locked-label">{{ $copy->get('premium_landing.locked_label') }}</span>
                        @if ($resource->course)
                            <span class="premium-landing__course-pill truncate">{{ $resource->course->name }}</span>
                        @endif
                    </div>
                    <p class="premium-landing__locked-title truncate">{{ $resource->title }}</p>
                </div>
            </div>
            <div class="premium-landing__locked-hint">
                <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                </svg>
                <span>Unlock this resource and get unlimited study access below.</span>
            </div>
        </div>
    @endif

    @if ($isPremium)
        <div class="premium-landing__active">
            <div class="premium-landing__active-header">
                <div class="premium-landing__active-badge-icon" aria-hidden="true">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <span class="premium-landing__active-pill">
                            <span class="premium-landing__active-dot"></span>
                            ACTIVE MEMBER
                        </span>
                    </div>
                    <p class="premium-landing__active-title">{{ $copy->get('premium_landing.active_title') }}</p>
                </div>
            </div>
            <p class="premium-landing__active-body">{{ $copy->get('premium_landing.active_body') }}</p>
        </div>
    @else
        <header class="premium-landing__header">
            <div class="premium-landing__header-top">
                <span class="premium-landing__brand">{{ config('app.name') }}</span>
                <span class="premium-landing__badge">
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                    <span>{{ $copy->get('premium_landing.badge') }}</span>
                </span>
            </div>
            <h2 class="premium-landing__title">{{ $copy->get('premium_landing.title') }}</h2>
            <p class="premium-landing__subtitle">{{ $copy->get('premium_landing.subtitle') }}</p>

            <div class="premium-landing__highlights">
                <div class="premium-landing__highlight-item">
                    <span class="premium-landing__highlight-icon">⚡</span>
                    <span class="premium-landing__highlight-text">Instant Unlock</span>
                </div>
                <div class="premium-landing__highlight-item">
                    <span class="premium-landing__highlight-icon">🎯</span>
                    <span class="premium-landing__highlight-text">All Courses</span>
                </div>
                <div class="premium-landing__highlight-item">
                    <span class="premium-landing__highlight-icon">🤖</span>
                    <span class="premium-landing__highlight-text">Bot + Mini App</span>
                </div>
            </div>
        </header>

        <div class="premium-landing__features-wrapper">
            <div class="premium-landing__features-top">
                <span class="premium-landing__features-heading">MEMBER PRIVILEGES</span>
                <span class="premium-landing__features-tag">ALL INCLUDED</span>
            </div>

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
        </div>

        <div class="premium-landing__plan">
            <div class="premium-landing__plan-card premium-landing__plan-card--selected" aria-current="true">
                <div class="premium-landing__plan-header">
                    <div class="min-w-0">
                        <span class="premium-landing__plan-tag">FULL ACCESS</span>
                        <p class="premium-landing__plan-label">{{ $copy->get('premium_landing.one_time') }}</p>
                        <p class="premium-landing__plan-amount">{{ $formattedPrice }}</p>
                    </div>
                    <div class="premium-landing__plan-radio" aria-hidden="true">
                        <div class="premium-landing__plan-radio-dot"></div>
                    </div>
                </div>

                <div class="premium-landing__plan-perks">
                    <div class="premium-landing__plan-perk">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143z" clip-rule="evenodd" />
                        </svg>
                        <span>Full access to all courses, quizzes & timed exams</span>
                    </div>
                    <div class="premium-landing__plan-perk">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143z" clip-rule="evenodd" />
                        </svg>
                        <span>Instant automated activation via Telegram bot</span>
                    </div>
                </div>
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
                    <span>{{ $copy->get('premium_landing.cta') }}</span>
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10z" clip-rule="evenodd" />
                    </svg>
                </a>
            @else
                <p class="premium-landing__bar-unavailable">{{ $copy->get('premium_landing.unavailable') }}</p>
            @endif
        </div>
    @endif
</div>
