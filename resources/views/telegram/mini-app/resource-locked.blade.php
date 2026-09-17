<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$resource->title"
    :back-url="$resource->course ? route('tg.courses.show', $resource->course) : route('tg.browse')"
>
    <div class="space-y-4 px-4">
        <div class="tg-section" style="margin-inline: 0;">
            <div class="tg-cell" style="cursor: default;">
                <span class="tg-cell-body">
                    <span class="tg-cell-title">Premium resource</span>
                    <span class="tg-cell-subtitle">Unlock for {{ $price }} ETB or refer {{ $required }} students. Progress: {{ $progress }} / {{ $required }}</span>
                </span>
            </div>
        </div>

        <a href="{{ route('tg.premium') }}" class="tg-btn">
            {{ $copy->get('keyboard.premium') }}
        </a>
    </div>
</x-telegram.mini-app.layout>
