<x-telegram.mini-app.layout :copy="$copy" :active-nav="$activeNav" :title="$resource->title">
    <div class="space-y-5">
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm leading-relaxed text-amber-950">
            <p class="font-semibold">Premium resource</p>
            <p class="mt-2">Unlock for {{ $price }} ETB or refer {{ $required }} students.</p>
            <p class="mt-1">Progress: {{ $progress }} / {{ $required }}</p>
        </div>

        <a href="{{ route('tg.premium') }}" class="inline-flex w-full items-center justify-center rounded-2xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white">
            {{ $copy->get('keyboard.premium') }}
        </a>
    </div>
</x-telegram.mini-app.layout>
