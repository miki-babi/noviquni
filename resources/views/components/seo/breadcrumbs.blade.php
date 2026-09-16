@if ($items !== [])
    <nav aria-label="Breadcrumb" class="mb-6 text-xs font-medium text-text-muted">
        <ol class="flex flex-wrap items-center gap-2">
            @foreach ($items as $index => $item)
                <li class="flex items-center gap-2">
                    @if ($index > 0)
                        <span aria-hidden="true">/</span>
                    @endif
                    @if ($item['url'] && ! $loop->last)
                        <a href="{{ $item['url'] }}" class="hover:text-primary-200">{{ $item['name'] }}</a>
                    @else
                        <span class="text-text-secondary">{{ $item['name'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
