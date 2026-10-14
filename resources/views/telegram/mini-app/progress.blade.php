<x-telegram.mini-app.layout
    :copy="$copy"
    :user="$user"
    :active-nav="$activeNav"
    :title="$title"
    :back-url="$backUrl"
>
    <div class="study-page space-y-4 px-4 pb-6">
        <div class="study-card space-y-2">
            <p class="study-card-title">Your study report</p>
            <p class="text-sm text-text-secondary">
                Strengths, weaknesses, and how your scores are changing over time.
            </p>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-text-muted">Attempts</p>
                    <p class="text-xl font-semibold text-text-primary">{{ $report['summary']['attempts'] }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-text-muted">Average</p>
                    <p class="text-xl font-semibold text-text-primary">{{ $report['summary']['average_percent'] }}%</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-text-muted">Latest</p>
                    <p class="text-xl font-semibold text-text-primary">
                        {{ $report['summary']['latest_percent'] === null ? '—' : $report['summary']['latest_percent'].'%' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-text-muted">Trend</p>
                    <p class="text-xl font-semibold text-text-primary capitalize">{{ $report['summary']['trend'] }}</p>
                </div>
            </div>
        </div>

        <div class="study-card space-y-3">
            <p class="study-card-title">What to focus on</p>
            <ul class="space-y-2 text-sm text-text-secondary">
                @foreach ($report['focus'] as $item)
                    <li class="leading-relaxed">{{ $item }}</li>
                @endforeach
            </ul>
        </div>

        @if ($report['strengths'] !== [])
            <div class="study-card space-y-3">
                <p class="study-card-title">Strengths</p>
                <ul class="space-y-2">
                    @foreach ($report['strengths'] as $strength)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-text-primary">{{ $strength['label'] }}</span>
                            <span class="shrink-0 font-semibold text-primary-200">{{ $strength['accuracy'] }}%</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($report['weaknesses'] !== [])
            <div class="study-card space-y-3">
                <p class="study-card-title">Weaknesses</p>
                <ul class="space-y-3">
                    @foreach ($report['weaknesses'] as $weakness)
                        <li class="text-sm">
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-medium text-text-primary">{{ $weakness['label'] }}</span>
                                <span class="shrink-0 font-semibold text-red-500">{{ $weakness['accuracy'] }}%</span>
                            </div>
                            <p class="mt-1 text-text-secondary">{{ $weakness['focus'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($report['by_resource'] !== [])
            <div class="study-card space-y-3">
                <p class="study-card-title">By resource</p>
                <ul class="space-y-3">
                    @foreach ($report['by_resource'] as $row)
                        <li class="text-sm">
                            <p class="font-medium text-text-primary">{{ $row['title'] }}</p>
                            <p class="mt-1 text-text-secondary">
                                {{ $row['attempts'] }} attempt{{ $row['attempts'] === 1 ? '' : 's' }}
                                · best {{ $row['best_percent'] }}%
                                · latest {{ $row['latest_percent'] }}%
                                @if ($row['improving'])
                                    · improving
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($report['timeline'] !== [])
            <div class="study-card space-y-3">
                <p class="study-card-title">Recent attempts</p>
                <ul class="space-y-3">
                    @foreach ($report['timeline'] as $row)
                        <li class="flex items-start justify-between gap-3 text-sm">
                            <div>
                                <p class="font-medium text-text-primary">{{ $row['label'] }}</p>
                                <p class="text-text-muted">{{ $row['kind'] }} · {{ $row['date'] }}</p>
                            </div>
                            <span class="shrink-0 font-semibold text-text-primary">
                                {{ $row['score'] }}/{{ $row['total'] }}
                                <span class="text-text-muted">({{ $row['percent'] }}%)</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-telegram.mini-app.layout>
