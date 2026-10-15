<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$resource->title"
    :back-url="$resource->course ? route('tg.courses.show', $resource->course) : route('tg.browse')"
>
    <div class="study-page space-y-4">
        <div class="study-card study-card--coral study-continue space-y-2">
            <p class="study-card-title">Premium resource</p>
            <p class="text-sm font-semibold text-text-primary">{{ $resource->title }}</p>
            <p class="study-card-meta">
                {{ $resource->type->label() }}
                @if ($resource->course)
                    · {{ $resource->course->name }}
                @endif
            </p>
            @if (filled($resource->description))
                <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-text-secondary">{{ $resource->description }}</p>
            @endif
            <p class="study-card-meta mt-2">
                Unlock for {{ $price }} ETB or refer {{ $required }} students. Progress: {{ $progress }} / {{ $required }}
            </p>
        </div>

        <div class="study-card study-card--lime study-continue space-y-2">
            <p class="study-card-title">{{ $pitchTitle }}</p>
            <p class="whitespace-pre-line text-sm leading-relaxed text-text-secondary">{{ $pitchBody }}</p>
        </div>

        @if ($paymentBotUrl)
            <a href="{{ $paymentBotUrl }}" target="_blank" rel="noopener noreferrer" class="tg-btn">
                {{ $copy->get('opportunities.guidance_unlock_premium') }}
            </a>
        @else
            <p class="text-sm font-semibold text-primary-300">Payment is temporarily unavailable. Please try again later.</p>
        @endif
    </div>
</x-telegram.mini-app.layout>
