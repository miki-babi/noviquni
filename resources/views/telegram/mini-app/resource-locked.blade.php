<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$resource->title"
    :back-url="$resource->course ? route('tg.courses.show', $resource->course) : route('tg.browse')"
>
    <div class="study-page">
        <div class="study-card study-card--coral study-continue">
            <p class="study-card-title">Premium resource</p>
            <p class="study-card-meta mt-2">
                Unlock for {{ $price }} ETB or refer {{ $required }} students. Progress: {{ $progress }} / {{ $required }}
            </p>
        </div>

        <a href="{{ route('tg.premium') }}" class="tg-btn">
            {{ $copy->get('menu.premium') }}
        </a>
    </div>
</x-telegram.mini-app.layout>
